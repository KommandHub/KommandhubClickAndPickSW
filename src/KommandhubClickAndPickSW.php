<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW;

use Doctrine\DBAL\Connection;
use Kommandhub\ClickAndPickSW\Checkout\Payment\PayOnPickupPaymentHandler;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSalesChannelMapping\PickupLocationSalesChannelMappingDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Service\CustomFieldsInstaller;
use Shopware\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Rule\Rule;

class KommandhubClickAndPickSW extends Plugin
{
    final const RULE_ID = 'cde3be3c21d76fae830b2e815a6d82ef';
    final const PAYMENT_METHOD_ID = 'c76322cc1bff7828011f266d9b47f559';
    final const SHIPPING_METHOD_ID = '25b9d4e415428362abb32d0a7cba2a38';
    final const STATE_READY_FOR_PICKUP_ID = '4c91ff4dbeda28ae3d001663d4638f21';

    public function install(InstallContext $installContext): void
    {
        $this->getCustomFieldsInstaller()->install($installContext->getContext());
        $this->addPaymentMethod($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $tables = [
            PickupLocationSalesChannelMappingDefinition::ENTITY_NAME,
            PickupLocationDefinition::ENTITY_NAME,
        ];

        $connection = $this->container->get(Connection::class);
        foreach ($tables as $table) {
            $connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
        $this->getCustomFieldsInstaller()->addRelations($activateContext->getContext());
        parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
        parent::deactivate($deactivateContext);
    }

    private function addPaymentMethod(Context $context): void
    {
        if ($this->container === null) {
            return;
        }

        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentData = [
            [
                'id' => self::PAYMENT_METHOD_ID,
                // payment handler will be selected by the identifier
                'handlerIdentifier' => PayOnPickupPaymentHandler::class,
                'name' => 'Pay on pickup',
                'description' => 'Pay for your order when you pick it up at the store.',
                'pluginId' => $pluginId,
                'afterOrderEnabled' => true,
                'technicalName' => 'kommandhub_pay_on_pickup',
                'availabilityRule' => [
                    'id' => self::RULE_ID,
                    'name' => 'Payment method is self pick-up',
                    'priority' => 100,
                    'conditions' => [
                        [
                            'type' => ShippingMethodRule::RULE_NAME,
                            'value' => [
                                'operator' => Rule::OPERATOR_EQ,
                                'shippingMethodIds' => [self::SHIPPING_METHOD_ID],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->upsert($paymentData, $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        if ($this->container === null) {
            return;
        }

        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        if ($this->container === null) {
            return null;
        }

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PayOnPickupPaymentHandler::class));

        return $paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }

    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        if ($this->container->has(CustomFieldsInstaller::class)) {
            return $this->container->get(CustomFieldsInstaller::class);
        }

        return new CustomFieldsInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository')
        );
    }
}