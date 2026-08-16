<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Installer;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Installer\CustomFieldsInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\CustomField\CustomFieldTypes;

#[CoversClass(CustomFieldsInstaller::class)]
class CustomFieldsInstallerTest extends TestCase
{
    private EntityRepository&MockObject $customFieldSetRepository;

    private EntityRepository&MockObject $customFieldSetRelationRepository;

    private CustomFieldsInstaller $installer;

    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRelationRepository = $this->createMock(EntityRepository::class);
        $this->installer = new CustomFieldsInstaller(
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository
        );
        $this->context = Context::createDefaultContext();
    }

    public function testInstallCreatesCustomFieldSetWhenMissing(): void
    {
        $this->customFieldSetRepository
            ->expects(static::once())
            ->method('searchIds')
            ->with(static::isInstanceOf(Criteria::class), $this->context)
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));

        $this->customFieldSetRepository
            ->expects(static::once())
            ->method('upsert')
            ->with(
                static::callback(function (array $payload): bool {
                    $fieldset = $payload[0] ?? null;

                    return $fieldset['name'] === 'kommandhub_click_and_pick_fieldset'
                        && $fieldset['customFields'][0]['name'] === CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD
                        && $fieldset['customFields'][0]['type'] === CustomFieldTypes::ENTITY
                        && $fieldset['customFields'][0]['entity'] === PickupLocationDefinition::ENTITY_NAME;
                }),
                $this->context
            );

        $this->installer->install($this->context);
    }

    public function testInstallDoesNothingWhenCustomFieldSetAlreadyExists(): void
    {
        $this->customFieldSetRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['fieldset-id'], new Criteria(), $this->context));
        $this->customFieldSetRepository->expects(static::never())->method('upsert');

        $this->installer->install($this->context);
    }

    public function testAddRelationsCreatesMissingOrderRelation(): void
    {
        $this->customFieldSetRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['fieldset-id'], new Criteria(), $this->context));

        $this->customFieldSetRelationRepository
            ->expects(static::once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context, 0));

        $this->customFieldSetRelationRepository
            ->expects(static::once())
            ->method('upsert')
            ->with([[
                'customFieldSetId' => 'fieldset-id',
                'entityName' => OrderDefinition::ENTITY_NAME,
            ]], $this->context);

        $this->installer->addRelations($this->context);
    }

    public function testAddRelationsDoesNothingWhenRelationAlreadyExists(): void
    {
        $this->customFieldSetRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['fieldset-id'], new Criteria(), $this->context));

        $this->customFieldSetRelationRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['relation-id'], new Criteria(), $this->context, 1));

        $this->customFieldSetRelationRepository->expects(static::never())->method('upsert');

        $this->installer->addRelations($this->context);
    }

    public function testAddRelationsDoesNothingWhenNoCustomFieldSetExists(): void
    {
        $this->customFieldSetRepository
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $this->context));

        $this->customFieldSetRelationRepository->expects(static::never())->method('searchIds');
        $this->customFieldSetRelationRepository->expects(static::never())->method('upsert');

        $this->installer->addRelations($this->context);
    }
}
