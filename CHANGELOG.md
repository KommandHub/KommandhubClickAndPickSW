# 1.0.0

- Initial release of Click and Pick for Shopware 6.
- Pickup-location selection in checkout, filtered by active state and
  sales-channel assignment.
- `Self pick-up` shipping method and `Pay on pickup` payment method, with the
  payment method restricted to the pickup shipping context.
- `Pickup Locations` administration module (create/edit, sales-channel
  assignment, opening days/hours, contact and geo fields).
- `ready_for_pickup` order-delivery state with customer pickup-ready mail and
  admin pickup-order-placed notification, wired through Flow Builder.
- `pickup.order.placed` business event and the `kommandhub_pickup_location_id`
  order custom field.
