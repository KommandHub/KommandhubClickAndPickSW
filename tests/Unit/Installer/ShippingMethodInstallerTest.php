<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Installer;

use Kommandhub\ClickAndPickSW\Installer\ShippingMethodInstaller;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(ShippingMethodInstaller::class)]
class ShippingMethodInstallerTest extends TestCase
{
    private EntityRepository&MockObject $shippingMethodRepository;

    private EntityRepository&MockObject $deliveryTimeRepository;

    private EntityRepository&MockObject $ruleRepository;

    private ShippingMethodInstaller $installer;

    private Context $context;

    protected function setUp(): void
    {
        $this->shippingMethodRepository = $this->createMock(EntityRepository::class);
        $this->deliveryTimeRepository = $this->createMock(EntityRepository::class);
        $this->ruleRepository = $this->createMock(EntityRepository::class);
        $this->installer = new ShippingMethodInstaller(
            $this->shippingMethodRepository,
            $this->deliveryTimeRepository,
            $this->ruleRepository
        );
        $this->context = Context::createDefaultContext();
    }

    public function testInstallDoesNothingWhenShippingMethodAlreadyExists(): void
    {
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([KommandhubClickAndPickSW::SHIPPING_METHOD_ID], new Criteria(), $this->context));
        $this->shippingMethodRepository->expects(static::never())->method('create');

        $this->installer->install($this->context);
    }

    public function testInstallCreatesShippingMethodWhenMissing(): void
    {
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));
        $this->deliveryTimeRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['delivery-time-id'], new Criteria(), $this->context));
        $this->ruleRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['all-customers-rule-id'], new Criteria(), $this->context));
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('create')
            ->with(
                static::callback(function (array $payload): bool {
                    $shippingMethod = $payload[0] ?? null;
                    $price = $shippingMethod['prices'][0]['currencyPrice'][0] ?? null;

                    return $shippingMethod['id'] === KommandhubClickAndPickSW::SHIPPING_METHOD_ID
                        && $shippingMethod['technicalName'] === ShippingMethodInstaller::TECHNICAL_NAME
                        && $shippingMethod['deliveryTimeId'] === 'delivery-time-id'
                        && $shippingMethod['availabilityRuleId'] === 'all-customers-rule-id'
                        && $price['currencyId'] === Defaults::CURRENCY
                        && $price['gross'] === 0
                        && $price['net'] === 0;
                }),
                $this->context
            );

        $this->installer->install($this->context);
    }

    public function testInstallThrowsWhenNoDeliveryTimeExists(): void
    {
        $this->shippingMethodRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));
        $this->deliveryTimeRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));
        $this->shippingMethodRepository->expects(static::never())->method('create');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Delivery time not found');

        $this->installer->install($this->context);
    }

    public function testActivateUpdatesExistingShippingMethod(): void
    {
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([KommandhubClickAndPickSW::SHIPPING_METHOD_ID], new Criteria(), $this->context));
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('update')
            ->with([[
                'id' => KommandhubClickAndPickSW::SHIPPING_METHOD_ID,
                'active' => true,
            ]], $this->context);

        $this->installer->activate($this->context);
    }

    public function testDeactivateUpdatesExistingShippingMethod(): void
    {
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([KommandhubClickAndPickSW::SHIPPING_METHOD_ID], new Criteria(), $this->context));
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('update')
            ->with([[
                'id' => KommandhubClickAndPickSW::SHIPPING_METHOD_ID,
                'active' => false,
            ]], $this->context);

        $this->installer->deactivate($this->context);
    }

    public function testDeactivateDoesNothingWhenShippingMethodIsMissing(): void
    {
        $this->shippingMethodRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));
        $this->shippingMethodRepository->expects(static::never())->method('update');

        $this->installer->deactivate($this->context);
    }
}
