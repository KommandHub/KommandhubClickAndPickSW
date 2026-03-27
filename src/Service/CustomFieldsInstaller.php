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
        $this->customFieldSetRepository->upsert([
            self::CUSTOM_FIELDSET
        ], $context);
    }

    public function addRelations(Context $context): void
    {
        $this->customFieldSetRelationRepository->upsert(array_map(function (string $customFieldSetId) {
            return [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => OrderDefinition::ENTITY_NAME,
            ];
        }, $this->getCustomFieldSetIds($context)), $context);
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
}