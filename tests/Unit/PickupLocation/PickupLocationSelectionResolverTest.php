<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\PickupLocation;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Kommandhub\ClickAndPickSW\Listener\SwitchContextEventListener;
use Kommandhub\ClickAndPickSW\PickupLocation\PickupLocationSelectionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(PickupLocationSelectionResolver::class)]
class PickupLocationSelectionResolverTest extends TestCase
{
    private const SALES_CHANNEL_ID = '11111111111111111111111111111111';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private EntityRepository&MockObject $pickupLocationRepository;

    private PickupLocationSelectionResolver $resolver;

    protected function setUp(): void
    {
        $this->pickupLocationRepository = $this->createMock(EntityRepository::class);
        $this->resolver = new PickupLocationSelectionResolver($this->pickupLocationRepository);
    }

    public function testDetectsPickupShippingMethodByConfiguredId(): void
    {
        static::assertTrue($this->resolver->isPickupShippingMethod(
            $this->salesChannelContext($this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID))
        ));
        static::assertFalse($this->resolver->isPickupShippingMethod(
            $this->salesChannelContext($this->shippingMethod('different-shipping-id'))
        ));
    }

    public function testExtractsPickupLocationIdFromPrimaryExtensionKey(): void
    {
        $context = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct([SwitchContextEventListener::PICKUP_LOCATION_ID => ' ' . self::LOCATION_ID . ' '])
        );

        static::assertSame(self::LOCATION_ID, $this->resolver->extractPickupLocationId($context));
    }

    public function testFallsBackToLegacyExtensionKey(): void
    {
        $context = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct(['id' => self::LOCATION_ID])
        );

        static::assertSame(self::LOCATION_ID, $this->resolver->extractPickupLocationId($context));
    }

    public function testReturnsNullForMissingOrInvalidExtensionValues(): void
    {
        $missingExtensionContext = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID)
        );
        $invalidValueContext = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct([SwitchContextEventListener::PICKUP_LOCATION_ID => 123])
        );
        $blankValueContext = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct([SwitchContextEventListener::PICKUP_LOCATION_ID => '   '])
        );

        static::assertNull($this->resolver->extractPickupLocationId($missingExtensionContext));
        static::assertNull($this->resolver->extractPickupLocationId($invalidValueContext));
        static::assertNull($this->resolver->extractPickupLocationId($blankValueContext));
    }

    public function testResolvesValidPickupLocationForCurrentSalesChannel(): void
    {
        $context = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct([SwitchContextEventListener::PICKUP_LOCATION_ID => self::LOCATION_ID])
        );
        $pickupLocation = new PickupLocationEntity();
        $pickupLocation->setId(self::LOCATION_ID);

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    $filters = $criteria->getFilters();
                    $filter = $filters[0] ?? null;

                    if (!$filter instanceof MultiFilter || $criteria->getLimit() !== 1) {
                        return false;
                    }

                    $queries = $filter->getQueries();

                    return $filter->getOperator() === MultiFilter::CONNECTION_AND
                        && $queries[0] instanceof EqualsFilter
                        && $queries[0]->getField() === 'id'
                        && $queries[0]->getValue() === self::LOCATION_ID
                        && $queries[1] instanceof EqualsFilter
                        && $queries[1]->getField() === 'active'
                        && $queries[1]->getValue() === true
                        && $queries[2] instanceof EqualsFilter
                        && $queries[2]->getField() === 'salesChannels.id'
                        && $queries[2]->getValue() === self::SALES_CHANNEL_ID;
                }),
                $context->getContext()
            )
            ->willReturn($this->firstResult($pickupLocation));

        static::assertSame($pickupLocation, $this->resolver->resolve($context));
    }

    public function testResolveReturnsNullWhenNoValidLocationCanBeLoaded(): void
    {
        $missingIdContext = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID)
        );
        $invalidEntityContext = $this->salesChannelContext(
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            new ArrayStruct([SwitchContextEventListener::PICKUP_LOCATION_ID => self::LOCATION_ID])
        );

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $invalidEntityContext->getContext())
            ->willReturn($this->firstResult(new \stdClass()));

        static::assertNull($this->resolver->resolve($missingIdContext));
        static::assertNull($this->resolver->resolve($invalidEntityContext));
    }

    private function salesChannelContext(
        ShippingMethodEntity $shippingMethod,
        ?ArrayStruct $pickupLocationExtension = null
    ): SalesChannelContext&MockObject {
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getShippingMethod', 'getSalesChannelId'])
            ->getMock();
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        if ($pickupLocationExtension !== null) {
            $context->addExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION, $pickupLocationExtension);
        }

        return $context;
    }

    private function shippingMethod(string $id): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);

        return $shippingMethod;
    }

    private function firstResult(?object $first): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($first);

        return $result;
    }
}
