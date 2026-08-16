<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\Cart;

use Kommandhub\ClickAndPickSW\Checkout\Cart\Error\UnsupportedDeliveryMethodCartBlockerError;
use Kommandhub\ClickAndPickSW\Checkout\Cart\PayOnPickupCartProcessor;
use Kommandhub\ClickAndPickSW\Checkout\Payment\PayOnPickupPaymentHandler;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(PayOnPickupCartProcessor::class)]
#[UsesClass(UnsupportedDeliveryMethodCartBlockerError::class)]
#[UsesClass(PayOnPickupPaymentHandler::class)]
class PayOnPickupCartProcessorTest extends TestCase
{
    public function testIgnoresOtherPaymentMethods(): void
    {
        $processor = new PayOnPickupCartProcessor();
        $errors = new ErrorCollection();

        $processor->validate(
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
        $processor = new PayOnPickupCartProcessor();
        $errors = new ErrorCollection();

        $processor->validate(
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
        $processor = new PayOnPickupCartProcessor();
        $errors = new ErrorCollection();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('different-shipping-id');
        $shippingMethod->setName('Fallback Courier');
        $shippingMethod->setTranslated(['name' => ['unexpected']]);

        $processor->validate(
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

    public function testAllowsConfiguredPickupShippingMethod(): void
    {
        $processor = new PayOnPickupCartProcessor();
        $errors = new ErrorCollection();

        $processor->validate(
            new Cart('token'),
            $errors,
            $this->salesChannelContext(
                $this->paymentMethod(PayOnPickupPaymentHandler::class),
                $this->shippingMethod(KommandhubClickAndPickSW::SHIPPING_METHOD_ID, 'Self pick-up')
            )
        );

        static::assertCount(0, $errors);
    }

    private function salesChannelContext(
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod
    ): SalesChannelContext {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getShippingMethod')->willReturn($shippingMethod);

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
}
