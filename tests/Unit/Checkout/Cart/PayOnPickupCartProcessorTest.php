<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\Cart;

use Kommandhub\ClickAndPickSW\Checkout\Cart\Error\PickupLocationRequiredCartBlockerError;
use Kommandhub\ClickAndPickSW\Checkout\Cart\Error\UnsupportedDeliveryMethodCartBlockerError;
use Kommandhub\ClickAndPickSW\Checkout\Cart\PayOnPickupCartProcessor;
use Kommandhub\ClickAndPickSW\Checkout\Payment\PayOnPickupPaymentHandler;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Kommandhub\ClickAndPickSW\Listener\SwitchContextEventListener;
use Kommandhub\ClickAndPickSW\PickupLocation\PickupLocationSelectionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(PayOnPickupCartProcessor::class)]
#[UsesClass(PickupLocationRequiredCartBlockerError::class)]
#[UsesClass(PickupLocationSelectionResolver::class)]
#[UsesClass(UnsupportedDeliveryMethodCartBlockerError::class)]
#[UsesClass(PayOnPickupPaymentHandler::class)]
class PayOnPickupCartProcessorTest extends TestCase
{
    private const SALES_CHANNEL_ID = '11111111111111111111111111111111';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private EntityRepository&MockObject $pickupLocationRepository;

    private PayOnPickupCartProcessor $processor;

    protected function setUp(): void
    {
        $this->pickupLocationRepository = $this->createMock(EntityRepository::class);
        $this->processor = new PayOnPickupCartProcessor(
            new PickupLocationSelectionResolver($this->pickupLocationRepository)
        );
    }

    public function testIgnoresOtherPaymentMethods(): void
    {
        $errors = new ErrorCollection();

        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->processor->validate(
            new Cart('token'),
            $errors,
            $this->salesChannelContext(
                $this->paymentMethod('App\\OtherPaymentHandler'),
                $this->shippingMethod('different-shipping-id', 'Courier')
            )
        );

        static::assertCount(0, $errors);
    }

    public function testAddsBlockingErrorForUnsupportedShippingMethod(): void
    {
        $errors = new ErrorCollection();

        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->processor->validate(
            new Cart('token'),
            $errors,
            $this->salesChannelContext(
                $this->paymentMethod(PayOnPickupPaymentHandler::class),
                $this->shippingMethod('different-shipping-id', 'Courier')
            )
        );

        static::assertCount(1, $errors);
        $error = $errors->first();
        static::assertInstanceOf(UnsupportedDeliveryMethodCartBlockerError::class, $error);
        static::assertSame(['deliveryMethodName' => 'Courier'], $error->getParameters());
    }

    public function testFallsBackToShippingMethodNameWhenTranslationIsMissing(): void
    {
        $errors = new ErrorCollection();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('different-shipping-id');
        $shippingMethod->setName('Fallback Courier');
        $shippingMethod->setTranslated(['name' => ['unexpected']]);

        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->processor->validate(
            new Cart('token'),
            $errors,
            $this->salesChannelContext(
                $this->paymentMethod(PayOnPickupPaymentHandler::class),
                $shippingMethod
            )
        );

        static::assertCount(1, $errors);
        static::assertSame(
            ['deliveryMethodName' => 'Fallback Courier'],
            $errors->first()?->getParameters()
        );
    }

    public function testAllowsClickAndPickShippingWithValidPickupLocation(): void
    {
        $errors = new ErrorCollection();
        $context = $this->salesChannelContext(
            $this->paymentMethod(PayOnPickupPaymentHandler::class),
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID, 'Self pick-up'),
            new ArrayStruct([
                SwitchContextEventListener::PICKUP_LOCATION_ID => self::LOCATION_ID,
            ])
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

                    if (!$filter instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter) {
                        return false;
                    }

                    $queries = $filter->getQueries();

                    return $criteria->getLimit() === 1
                        && $queries[0] instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter
                        && $queries[0]->getField() === 'id'
                        && $queries[0]->getValue() === self::LOCATION_ID
                        && $queries[1] instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter
                        && $queries[1]->getField() === 'active'
                        && $queries[1]->getValue() === true
                        && $queries[2] instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter
                        && $queries[2]->getField() === 'salesChannels.id'
                        && $queries[2]->getValue() === self::SALES_CHANNEL_ID;
                }),
                $context->getContext()
            )
            ->willReturn($this->firstResult($pickupLocation));

        $this->processor->validate(new Cart('token'), $errors, $context);

        static::assertCount(0, $errors);
    }

    public function testAddsBlockingErrorWhenClickAndPickShippingHasNoPickupLocation(): void
    {
        $errors = new ErrorCollection();

        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->processor->validate(
            new Cart('token'),
            $errors,
            $this->salesChannelContext(
                $this->paymentMethod('App\\OtherPaymentHandler'),
                $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID, 'Self pick-up')
            )
        );

        static::assertCount(1, $errors);
        static::assertInstanceOf(PickupLocationRequiredCartBlockerError::class, $errors->first());
    }

    public function testAddsBlockingErrorWhenClickAndPickShippingHasInvalidPickupLocation(): void
    {
        $errors = new ErrorCollection();
        $context = $this->salesChannelContext(
            $this->paymentMethod('App\\OtherPaymentHandler'),
            $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID, 'Self pick-up'),
            new ArrayStruct([
                SwitchContextEventListener::PICKUP_LOCATION_ID => self::LOCATION_ID,
            ])
        );

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::isInstanceOf(Criteria::class),
                $context->getContext()
            )
            ->willReturn($this->firstResult(null));

        $this->processor->validate(new Cart('token'), $errors, $context);

        static::assertCount(1, $errors);
        static::assertInstanceOf(PickupLocationRequiredCartBlockerError::class, $errors->first());
    }

    private function salesChannelContext(
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod,
        ?ArrayStruct $pickupLocationExtension = null
    ): SalesChannelContext&MockObject {
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getPaymentMethod', 'getShippingMethod', 'getSalesChannelId'])
            ->getMock();
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        if ($pickupLocationExtension !== null) {
            $context->addExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION, $pickupLocationExtension);
        }

        return $context;
    }

    private function paymentMethod(string $handlerIdentifier): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier($handlerIdentifier);

        return $paymentMethod;
    }

    private function shippingMethod(string $id, string $name): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setTranslated(['name' => $name]);

        return $shippingMethod;
    }

    private function firstResult(?object $first): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($first);

        return $result;
    }
}
