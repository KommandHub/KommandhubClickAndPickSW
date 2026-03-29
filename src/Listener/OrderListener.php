<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Kommandhub\ClickAndPickSW\Migration\Migration1760113852PickupReadyMailTemplate;
use Kommandhub\ClickAndPickSW\Service\CustomFieldsInstaller;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class OrderListener
 *
 * Central listener handling all pickup-related order logic:
 *
 * Responsibilities:
 * - Persist selected pickup location to order (custom fields)
 * - Attach pickup location entity to loaded orders (extension)
 * - Send notification email to pickup location (admin)
 * - Dispatch domain event for pickup orders
 *
 * Design Notes:
 * - Follows SRP by delegating logic to small private methods
 * - Uses early returns to reduce nesting
 * - Safe for high-volume order processing
 */
readonly class OrderListener
{
    public const PICKUP_LOCATION_EXTENSION = 'pickupLocation';

    public function __construct(
        private EntityRepository $orderRepository,
        private EntityRepository $kommandhubPickupLocationRepository,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'Shopware\Core\Content\Mail\Service\MailService')]
        private AbstractMailService $mailService,
        private EntityRepository $mailTemplateRepository,
        private SystemConfigService $systemConfigService,
    ) {}

    /**
     * Handle order placement.
     *
     * Flow:
     * 1. Extract pickup location from context
     * 2. Persist it to order custom fields
     * 3. Notify pickup location via email
     * 4. Dispatch domain event
     */
    #[AsEventListener(event: CheckoutOrderPlacedEvent::class)]
    public function onCheckoutOrderPlacedEvent(CheckoutOrderPlacedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $pickupLocationId = $this->extractPickupLocationId($salesChannelContext);

        if ($pickupLocationId === null) {
            return;
        }

        $order = $event->getOrder();

        $this->updateOrderWithPickupLocation($order, $pickupLocationId, $context);
        $this->sendNotificationToAdmin($pickupLocationId, $order, $context);
        $this->dispatchPickupOrderPlacedEvent($order, $salesChannelContext);
    }

    /**
     * Attach pickup location entity to orders when loaded.
     *
     * Enables:
     * $order.extensions.pickupLocation
     */
    #[AsEventListener(event: OrderEvents::ORDER_LOADED_EVENT)]
    public function onOrderLoaded(EntityLoadedEvent $event): void
    {
        $orderLocationMap = $this->extractOrderLocationMap($event);

        if ($orderLocationMap === []) {
            return;
        }

        $pickupLocations = $this->fetchPickupLocationsById(
            array_values($orderLocationMap),
            $event->getContext()
        );

        if ($pickupLocations === []) {
            return;
        }

        $this->attachPickupLocations($event, $orderLocationMap, $pickupLocations);
    }

    /**
     * Send notification email to pickup location.
     */
    private function sendNotificationToAdmin(
        string $pickupLocationId,
        OrderEntity $order,
        Context $context
    ): void {
        $pickupLocation = $this->getPickupLocation($pickupLocationId, $context);
        if ($pickupLocation === null) {
            return;
        }

        $template = $this->resolveAdminMailTemplate($context);
        if ($template === null) {
            return;
        }

        $data = $this->buildMailData($pickupLocation, $order, $template, $context);

        $this->mailService->send($data, $context, $data['mailTemplateData']);
    }

    /**
     * Fetch pickup location entity.
     */
    private function getPickupLocation(string $id, Context $context): ?PickupLocationEntity
    {
        $entity = $this->kommandhubPickupLocationRepository
            ->search(new Criteria([$id]), $context)
            ->first();

        return $entity instanceof PickupLocationEntity ? $entity : null;
    }

    /**
     * Resolve admin mail template safely.
     */
    private function resolveAdminMailTemplate(Context $context): ?MailTemplateEntity
    {
        try {
            return $this->getAdminMailTemplate($context);
        } catch (\Throwable) {
            // @todo: add logging
            return null;
        }
    }

    /**
     * Build email payload.
     */
    private function buildMailData(
        PickupLocationEntity $pickupLocation,
        OrderEntity $order,
        MailTemplateEntity $template,
        Context $context
    ): array {
        return [
            'subject' => $template->getSubject(),
            'senderName' => $template->getSenderName(),
            'senderEmail' => $this->getSenderEmail($order),
            'recipients' => [
                $pickupLocation->getEmail() => $pickupLocation->getName()
            ],
            'salesChannelId' => $order->getSalesChannelId(),
            'mailTemplateData' => [
                'order' => $order,
                'customer' => $order->getOrderCustomer(),
                'pickupLocation' => $pickupLocation,
            ],
            'contentHtml' => $template->getContentHtml(),
            'contentPlain' => $template->getContentPlain(),
            'attachmentsConfig' => new MailAttachmentsConfig(
                $context,
                $template,
                new MailSendSubscriberConfig(false),
                [],
                $order->getId()
            ),
        ];
    }

    /**
     * Get sender email from system config.
     */
    private function getSenderEmail(OrderEntity $order): ?string
    {
        return $this->systemConfigService->get(
            'core.basicInformation.email',
            $order->getSalesChannelId()
        );
    }

    /**
     * Extract pickup location ID from context extension.
     */
    private function extractPickupLocationId(SalesChannelContext $context): ?string
    {
        $extension = $context->getExtension(self::PICKUP_LOCATION_EXTENSION);

        return $extension?->getVars()['id'] ?? null;
    }

    /**
     * Persist pickup location ID to order custom fields.
     */
    private function updateOrderWithPickupLocation(
        OrderEntity $order,
        string $pickupLocationId,
        Context $context
    ): void {
        $customFields = $order->getCustomFields() ?? [];

        $this->orderRepository->update([[
            'id' => $order->getId(),
            'customFields' => array_merge($customFields, [
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => $pickupLocationId
            ]),
        ]], $context);
    }

    /**
     * Dispatch domain event for pickup orders.
     */
    private function dispatchPickupOrderPlacedEvent(
        OrderEntity $order,
        SalesChannelContext $context
    ): void {
        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $method = $delivery->getShippingMethod();

            if ($method?->getId() !== KommandhubClickAndPickSW::SHIPPING_METHOD_ID) {
                continue;
            }

            $this->eventDispatcher->dispatch(
                new PickupOrderPlacedEvent($context, $order),
                PickupOrderPlacedEvent::EVENT_NAME
            );

            break;
        }
    }

    /**
     * Retrieve admin mail template.
     */
    private function getAdminMailTemplate(Context $context): MailTemplateEntity
    {
        $criteria = (new Criteria([
            Migration1760113852PickupReadyMailTemplate::ADMIN_ORDER_PLACED_TEMPLATE_ID
        ]))->addAssociation('mailTemplateType')->setLimit(1);

        $template = $this->mailTemplateRepository->search($criteria, $context)->first();

        if (!$template instanceof MailTemplateEntity) {
            throw new \RuntimeException('Mail template not found');
        }

        return $template;
    }

    /**
     * Extract order → pickup location mapping.
     *
     * @return array<string, string>
     */
    private function extractOrderLocationMap(EntityLoadedEvent $event): array
    {
        $map = [];

        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $id = $order->getCustomFieldsValue(
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD
            );

            if (is_string($id) && $id !== '') {
                $map[$order->getId()] = $id;
            }
        }

        return $map;
    }

    /**
     * Fetch pickup locations indexed by ID.
     *
     * @return array<string, PickupLocationEntity>
     */
    private function fetchPickupLocationsById(array $ids, Context $context): array
    {
        $entities = $this->kommandhubPickupLocationRepository
            ->search(new Criteria(array_unique($ids)), $context)
            ->getEntities();

        $mapped = [];

        foreach ($entities as $entity) {
            $mapped[$entity->getId()] = $entity;
        }

        return $mapped;
    }

    /**
     * Attach pickup location entities to orders as extensions.
     */
    private function attachPickupLocations(
        EntityLoadedEvent $event,
        array $orderLocationMap,
        array $locations
    ): void {
        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $locationId = $orderLocationMap[$order->getId()] ?? null;

            if ($locationId && isset($locations[$locationId])) {
                $order->addExtension(self::PICKUP_LOCATION_EXTENSION, $locations[$locationId]);
            }
        }
    }
}