<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Service;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'kommandhub_click_and_pick_fieldset';

    /**
     * The custom field key used to store the pickup location ID in the order.
     */
    public const ORDER_PICKUP_LOCATION_CUSTOM_FIELD = 'kommandhub_pickup_location_id';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Click & Pick',
                'de-DE' => 'Click & Pick',
                Defaults::LANGUAGE_SYSTEM => 'Click & Pick'
            ]
        ],
        'customFields' => [
            [
                'name' => self::ORDER_PICKUP_LOCATION_CUSTOM_FIELD,
                'type' => CustomFieldTypes::ENTITY,
                'entity' => PickupLocationDefinition::ENTITY_NAME,
                'config' => [
                    'label' => [
                        'en-GB' => 'Pickup Location',
                        'de-DE' => 'Abholort',
                        Defaults::LANGUAGE_SYSTEM => 'Pickup Location'
                    ],
                    'customFieldPosition' => 1
                ]
            ]
        ]
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        if ($this->customFieldSetExists($context)) {
            return;
        }

        $this->customFieldSetRepository->upsert([
            self::CUSTOM_FIELDSET
        ], $context);
    }

    public function addRelations(Context $context): void
    {
        $relationsToInsert = [];

        foreach ($this->getCustomFieldSetIds($context) as $customFieldSetId) {
            if ($this->customFieldSetRelationExists($context, $customFieldSetId, OrderDefinition::ENTITY_NAME)) {
                continue;
            }

            $relationsToInsert[] = [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => OrderDefinition::ENTITY_NAME,
            ];
        }

        if ($relationsToInsert === []) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert($relationsToInsert, $context);
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function customFieldSetExists(Context $context): bool
    {
        return $this->getCustomFieldSetIds($context) !== [];
    }

    private function customFieldSetRelationExists(Context $context, string $customFieldSetId, string $entityName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}