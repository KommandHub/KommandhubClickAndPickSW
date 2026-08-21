<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Checkout\PickupSelection;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Persists the customer's pickup selection in the sales-channel context payload
 * via Shopware's own {@see SalesChannelContextPersister} — the same store that
 * holds the selected shipping and payment method.
 *
 * Using the persister (rather than raw SQL) means the selection inherits
 * Shopware's context lifecycle for free:
 *  - keyed by (token, sales channel) → never leaks across sales channels;
 *  - scoped to the customer when logged in (`token OR customer_id`) → survives
 *    the guest→customer token change on login and never leaks between customers;
 *  - merged into the existing payload → leaves shipping/payment selection intact.
 *
 * The selection is cleared once its cart becomes an order (see the order
 * listener), so a fresh cart never inherits a previous cart's pickup choice.
 */
readonly class PickupContextStorage
{
    public function __construct(
        private SalesChannelContextPersister $contextPersister,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function load(SalesChannelContext $context): StoredPickupSelection
    {
        $payload = $this->contextPersister->load(
            $context->getToken(),
            $context->getSalesChannelId(),
            $this->resolveCustomerId($context),
        );

        return new StoredPickupSelection(
            $this->normalize($payload[PickupContextKeys::LOCATION_ID] ?? null),
            $this->normalize($payload[PickupContextKeys::TIME] ?? null),
            $this->normalize($payload[PickupContextKeys::COMMENT] ?? null),
        );
    }

    /**
     * @throws \JsonException
     */
    public function save(SalesChannelContext $context, StoredPickupSelection $selection): void
    {
        $this->contextPersister->save(
            $context->getToken(),
            [
                PickupContextKeys::LOCATION_ID => $selection->pickupLocationId,
                PickupContextKeys::TIME => $selection->pickupTime,
                PickupContextKeys::COMMENT => $selection->comment,
            ],
            $context->getSalesChannelId(),
            $this->resolveCustomerId($context),
        );
    }

    /**
     * Clear the pickup selection. The null values overwrite the stored keys and
     * are dropped by the persister on the next load (it array_filters the payload).
     *
     * @throws \JsonException
     */
    public function clear(SalesChannelContext $context): void
    {
        $this->save($context, new StoredPickupSelection());
    }

    /**
     * Scope the payload to the customer only when genuinely logged in —
     * while an admin never impersonates the customer (permissions set), so an agent's
     * context can't overwrite the customer's stored selection.
     */
    private function resolveCustomerId(SalesChannelContext $context): ?string
    {
        $customer = $context->getCustomer();

        return $customer !== null && $context->getPermissions() === [] ? $customer->getId() : null;
    }

    private function normalize(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
