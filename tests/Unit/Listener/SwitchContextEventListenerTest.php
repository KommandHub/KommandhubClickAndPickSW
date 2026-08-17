<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Listener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Kommandhub\ClickAndPickSW\Listener\SwitchContextEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(SwitchContextEventListener::class)]
class SwitchContextEventListenerTest extends TestCase
{
    private const TOKEN = 'context-token';
    private const SALES_CHANNEL_ID = '11111111111111111111111111111111';
    private const PICKUP_LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private DataValidator&MockObject $validator;

    private SalesChannelContextPersister&MockObject $contextPersister;

    private Connection&MockObject $connection;

    private SwitchContextEventListener $listener;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(DataValidator::class);
        $this->contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $this->connection = $this->createMock(Connection::class);

        $this->listener = new SwitchContextEventListener(
            $this->validator,
            $this->contextPersister,
            $this->connection
        );
    }

    public function testOnSwitchContextClearsPickupLocationWhenNoNewIdProvided(): void
    {
        $context = $this->salesChannelContext(customerId: 'customer-id');
        $this->expectPayloadFetch(['existing' => 'value']);

        $this->validator->expects(static::never())->method('validate');
        $this->contextPersister
            ->expects(static::once())
            ->method('save')
            ->with(
                self::TOKEN,
                ['existing' => 'value', SwitchContextEventListener::PICKUP_LOCATION_ID => null],
                self::SALES_CHANNEL_ID,
                'customer-id'
            );

        $this->listener->onSwitchContext(
            new SwitchContextEvent(
                new RequestDataBag(),
                $context,
                new DataValidationDefinition(),
                []
            )
        );
    }

    public function testOnSwitchContextValidatesAndPersistsPickupLocation(): void
    {
        $context = $this->salesChannelContext(permissions: ['admin' => true]);
        $this->expectPayloadFetch(['existing' => 'value']);

        $this->validator
            ->expects(static::once())
            ->method('validate')
            ->with(
                [SwitchContextEventListener::PICKUP_LOCATION_ID => self::PICKUP_LOCATION_ID],
                static::callback(function (DataValidationDefinition $definition) use ($context): bool {
                    $constraint = $definition->getProperty(SwitchContextEventListener::PICKUP_LOCATION_ID)[0] ?? null;

                    if (!$constraint instanceof EntityExists) {
                        return false;
                    }

                    $criteria = $constraint->getCriteria();
                    $filters = $criteria->getFilters();
                    $multiFilter = $filters[0] ?? null;

                    if (!$multiFilter instanceof MultiFilter || $criteria->getLimit() !== 1) {
                        return false;
                    }

                    $queries = $multiFilter->getQueries();

                    return $definition->getName() === 'kommandhub_click_and_pick.context_switch'
                        && $constraint->getEntity() === 'kommandhub_pickup_location'
                        && $constraint->getContext() === $context->getContext()
                        && $multiFilter->getOperator() === MultiFilter::CONNECTION_AND
                        && $queries[0] instanceof EqualsFilter
                        && $queries[0]->getField() === 'id'
                        && $queries[0]->getValue() === self::PICKUP_LOCATION_ID
                        && $queries[1] instanceof EqualsFilter
                        && $queries[1]->getField() === 'active'
                        && $queries[1]->getValue() === true
                        && $queries[2] instanceof EqualsFilter
                        && $queries[2]->getField() === 'salesChannels.id'
                        && $queries[2]->getValue() === self::SALES_CHANNEL_ID;
                })
            );
        $this->contextPersister
            ->expects(static::once())
            ->method('save')
            ->with(
                self::TOKEN,
                ['existing' => 'value', SwitchContextEventListener::PICKUP_LOCATION_ID => self::PICKUP_LOCATION_ID],
                self::SALES_CHANNEL_ID,
                null
            );

        $this->listener->onSwitchContext(
            new SwitchContextEvent(
                new RequestDataBag([SwitchContextEventListener::PICKUP_LOCATION_ID => self::PICKUP_LOCATION_ID]),
                $context,
                new DataValidationDefinition(),
                []
            )
        );
    }

    public function testOnSwitchContextDoesNothingWhenNoStoredPayloadExists(): void
    {
        $this->expectPayloadFetch(null);
        $this->validator->expects(static::never())->method('validate');
        $this->contextPersister->expects(static::never())->method('save');

        $this->listener->onSwitchContext(
            new SwitchContextEvent(
                new RequestDataBag(),
                $this->salesChannelContext(),
                new DataValidationDefinition(),
                []
            )
        );
    }

    public function testOnSwitchContextDoesNotPersistExpiredPayload(): void
    {
        $this->expectPayloadFetch(['expired' => true]);
        $this->validator->expects(static::never())->method('validate');
        $this->contextPersister->expects(static::never())->method('save');

        $this->listener->onSwitchContext(
            new SwitchContextEvent(
                new RequestDataBag(),
                $this->salesChannelContext(),
                new DataValidationDefinition(),
                []
            )
        );
    }

    public function testOnSalesChannelContextResolvedAddsPickupExtension(): void
    {
        $context = $this->salesChannelContext();
        $this->expectPayloadFetch([SwitchContextEventListener::PICKUP_LOCATION_ID => self::PICKUP_LOCATION_ID]);

        $this->listener->onSalesChannelContextResolved(
            new SalesChannelContextResolvedEvent($context, self::TOKEN)
        );

        $extension = $context->getExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION);

        static::assertNotNull($extension);
        static::assertSame(self::PICKUP_LOCATION_ID, $extension->getVars()['id']);
        static::assertSame(
            self::PICKUP_LOCATION_ID,
            $extension->getVars()[SwitchContextEventListener::PICKUP_LOCATION_ID]
        );
    }

    public function testOnSalesChannelContextResolvedIgnoresMissingPayload(): void
    {
        $context = $this->salesChannelContext();
        $this->expectPayloadFetch(null);

        $this->listener->onSalesChannelContextResolved(
            new SalesChannelContextResolvedEvent($context, self::TOKEN)
        );

        static::assertNull($context->getExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION));
    }

    public function testOnSalesChannelContextResolvedIgnoresPayloadWithoutPickupLocation(): void
    {
        $context = $this->salesChannelContext();
        $this->expectPayloadFetch(['somethingElse' => true]);

        $this->listener->onSalesChannelContextResolved(
            new SalesChannelContextResolvedEvent($context, self::TOKEN)
        );

        static::assertNull($context->getExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function expectPayloadFetch(?array $payload): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects(static::once())
            ->method('fetchOne')
            ->willReturn($payload === null ? false : json_encode($payload, \JSON_THROW_ON_ERROR));

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'andWhere', 'setParameter', 'executeQuery'])
            ->getMock();
        $queryBuilder->method('select')->with('payload')->willReturnSelf();
        $queryBuilder->method('from')->with('sales_channel_api_context')->willReturnSelf();
        $queryBuilder->method('where')->with('token = :token')->willReturnSelf();
        $queryBuilder->method('andWhere')->with('sales_channel_id = :salesChannelId')->willReturnSelf();
        $queryBuilder->expects(static::exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use ($queryBuilder) {
                if ($key === 'token') {
                    static::assertSame(self::TOKEN, $value);
                }

                if ($key === 'salesChannelId') {
                    static::assertSame(Uuid::fromHexToBytes(self::SALES_CHANNEL_ID), $value);
                }

                return $queryBuilder;
            });
        $queryBuilder->expects(static::once())->method('executeQuery')->willReturn($result);

        $this->connection
            ->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
    }

    /**
     * @param array<string, bool> $permissions
     */
    private function salesChannelContext(?string $customerId = null, array $permissions = []): SalesChannelContext
    {
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getToken', 'getSalesChannelId', 'getCustomer', 'getPermissions'])
            ->getMock();
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getToken')->willReturn(self::TOKEN);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getPermissions')->willReturn($permissions);

        $customer = null;

        if ($customerId !== null) {
            $customer = new CustomerEntity();
            $customer->setId($customerId);
        }

        $context->method('getCustomer')->willReturn($customer);

        return $context;
    }
}
