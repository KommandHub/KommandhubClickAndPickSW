<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Installer;

use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Creates and maintains the "Self pick-up" shipping method (a free, self-service
 * delivery method). Idempotent: the method and its delivery time use fixed ids,
 * so re-running on every install/update is a no-op once they exist.
 */
readonly class ShippingMethodInstaller
{
    public const TECHNICAL_NAME = 'kommandhub_self_pickup';

    public function __construct(
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $deliveryTimeRepository,
        private EntityRepository $ruleRepository,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function install(Context $context): void
    {
        // Already present (existing install or update): leave it untouched —
        // historical orders reference it and merchants may have customised it.
        if ($this->shippingMethodExists($context)) {
            return;
        }

        $this->shippingMethodRepository->create([
            [
                'id' => KommandhubClickAndPickSW::SHIPPING_METHOD_ID,
                'active' => true,
                'technicalName' => self::TECHNICAL_NAME,
                'name' => 'Self pick-up',
                'description' => 'Pick up your order yourself from the selected location.',
                'deliveryTimeId' => $this->ensureDeliveryTime($context),
                'availabilityRuleId' => $this->getAllCustomersRuleId($context),
                'prices' => [
                    [
                        'calculation' => 1,
                        'quantityStart' => 0,
                        'currencyPrice' => [
                            [
                                'currencyId' => Defaults::CURRENCY,
                                'gross' => 0,
                                'net' => 0,
                                'linked' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    public function activate(Context $context): void
    {
        $this->setActive(true, $context);
    }

    public function deactivate(Context $context): void
    {
        $this->setActive(false, $context);
    }

    private function setActive(bool $active, Context $context): void
    {
        if (!$this->shippingMethodExists($context)) {
            return;
        }

        $this->shippingMethodRepository->update([
            [
                'id' => KommandhubClickAndPickSW::SHIPPING_METHOD_ID,
                'active' => $active,
            ],
        ], $context);
    }

    private function shippingMethodExists(Context $context): bool
    {
        return $this->shippingMethodRepository
            ->searchIds(new Criteria([KommandhubClickAndPickSW::SHIPPING_METHOD_ID]), $context)
            ->firstId() !== null;
    }

    /**
     * @throws \Exception
     */
    private function ensureDeliveryTime(Context $context): string
    {
        $deliveryTimeId = $this->deliveryTimeRepository
            ->searchIds(new Criteria(), $context)
            ->firstId();

        if ($deliveryTimeId !== null) {
            return $deliveryTimeId;
        }

        throw new \Exception('Delivery time not found');
    }

    private function getAllCustomersRuleId(Context $context): ?string
    {
        return $this->ruleRepository
            ->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('name', 'All customers'))->setLimit(1),
                $context
            )
            ->firstId();
    }
}
