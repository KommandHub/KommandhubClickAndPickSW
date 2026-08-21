<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\Payment;

use Kommandhub\ClickAndPickSW\Checkout\Payment\PayOnPickupPaymentHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;

#[CoversClass(PayOnPickupPaymentHandler::class)]
class PayOnPickupPaymentHandlerTest extends TestCase
{
    public function testIsDefaultPaymentHandler(): void
    {
        static::assertInstanceOf(DefaultPayment::class, new PayOnPickupPaymentHandler());
    }
}
