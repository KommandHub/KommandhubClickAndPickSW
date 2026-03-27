<?php

declare (strict_types = 1);

namespace Kommandhub\ClickAndPickSW\Checkout\Cart;

use Kommandhub\ClickAndPickSW\Checkout\Cart\Error\UnsupportedDeliveryMethodCartBlockerError;
use Kommandhub\ClickAndPickSW\Checkout\Payment\PayOnPickupPaymentHandler;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.cart.validator')]
class PayOnPickupCartProcessor implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();

        if ($paymentMethod->getHandlerIdentifier() !== PayOnPickupPaymentHandler::class)
        {
            return;
        }

        // Throw an error if the selected delivery method is not "Click & Pick"
        $deliveryMethod = $context->getShippingMethod();

        if ($deliveryMethod->getId() !== KommandhubClickAndPickSW::SHIPPING_METHOD_ID) {
            $errors->add(new UnsupportedDeliveryMethodCartBlockerError(
                $deliveryMethod->getTranslation('name')
            ));
        }
    }
}