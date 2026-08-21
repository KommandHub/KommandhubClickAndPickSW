<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupContextKeys;
use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupContextStorage;
use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\StoredPickupSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(PickupContextStorage::class)]
#[UsesClass(StoredPickupSelection::class)]
class PickupContextStorageTest extends TestCase
{
    private const TOKEN = 'context-token';
    private const SALES_CHANNEL_ID = '11111111111111111111111111111111';
    private const CUSTOMER_ID = '22222222222222222222222222222222';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private SalesChannelContextPersister&MockObject $persister;

    private PickupContextStorage $storage;

    protected function setUp(): void
    {
        $this->persister = $this->createMock(SalesChannelContextPersister::class);
        $this->storage = new PickupContextStorage($this->persister);
    }

    public function testLoadMapsPayloadIntoSelection(): void
    {
        $this->persister
            ->expects(static::once())
            ->method('load')
            ->with(self::TOKEN, self::SALES_CHANNEL_ID, null)
            ->willReturn([
                PickupContextKeys::LOCATION_ID => self::LOCATION_ID,
                PickupContextKeys::TIME => '2024-06-03T10:00:00+01:00',
                PickupContextKeys::COMMENT => 'Ring the bell',
                'shippingMethodId' => 'unrelated',
            ]);

        $selection = $this->storage->load($this->context());

        static::assertSame(self::LOCATION_ID, $selection->pickupLocationId);
        static::assertSame('2024-06-03T10:00:00+01:00', $selection->pickupTime);
        static::assertSame('Ring the bell', $selection->comment);
    }

    public function testLoadReturnsEmptySelectionWhenPayloadHasNoPickup(): void
    {
        $this->persister->method('load')->willReturn(['shippingMethodId' => 'x']);

        $selection = $this->storage->load($this->context());

        static::assertTrue($selection->isEmpty());
        static::assertNull($selection->pickupTime);
        static::assertNull($selection->comment);
    }

    public function testSaveWritesPickupKeysScopedToCustomer(): void
    {
        $this->persister
            ->expects(static::once())
            ->method('save')
            ->with(
                self::TOKEN,
                [
                    PickupContextKeys::LOCATION_ID => self::LOCATION_ID,
                    PickupContextKeys::TIME => '2024-06-03T10:00:00+01:00',
                    PickupContextKeys::COMMENT => 'Ring the bell',
                ],
                self::SALES_CHANNEL_ID,
                self::CUSTOMER_ID
            );

        $this->storage->save(
            $this->context(customerId: self::CUSTOMER_ID),
            new StoredPickupSelection(self::LOCATION_ID, '2024-06-03T10:00:00+01:00', 'Ring the bell')
        );
    }

    public function testClearWritesNullPickupKeys(): void
    {
        $this->persister
            ->expects(static::once())
            ->method('save')
            ->with(
                self::TOKEN,
                [
                    PickupContextKeys::LOCATION_ID => null,
                    PickupContextKeys::TIME => null,
                    PickupContextKeys::COMMENT => null,
                ],
                self::SALES_CHANNEL_ID,
                null
            );

        $this->storage->clear($this->context());
    }

    public function testDoesNotScopeToCustomerWhileImpersonating(): void
    {
        // An admin impersonating the customer has permissions set — the stored
        // selection must not be attributed to (or overwrite) the customer's own.
        $this->persister
            ->expects(static::once())
            ->method('save')
            ->with(self::TOKEN, static::anything(), self::SALES_CHANNEL_ID, null);

        $this->storage->clear($this->context(customerId: self::CUSTOMER_ID, permissions: ['impersonate' => true]));
    }

    /**
     * @param array<string, bool> $permissions
     */
    private function context(?string $customerId = null, array $permissions = []): SalesChannelContext&MockObject
    {
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getToken', 'getSalesChannelId', 'getCustomer', 'getPermissions'])
            ->getMock();

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
