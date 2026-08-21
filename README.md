<p align="center">
  <a href="https://kommandhub.com" target="_blank">
    <img src="src/Resources/config/kommandhub.png" alt="Kommandhub Logo">
  </a>
</p>

# Click and Pick plugin for Shopware 6

`KommandhubClickAndPickSW` adds a complete click-and-collect flow to Shopware:
- pickup location selection in checkout,
- a dedicated `Pay on pickup` payment method,
- a dedicated `Self pick-up` shipping method,
- pickup-related order state and mail flow automation.

## What This Plugin Does

### Storefront
- Shows a pickup location selector during checkout for the plugin shipping method.
- Filters selectable locations by active status and sales channel assignment.
- Shows location details (address, opening schedule, optional contact details).
- Lets the customer choose a pickup date and time (limited to the location's
  timezone-aware opening schedule) and add optional pickup instructions.
- Persists the pickup selection in the sales channel context and re-shows it on
  the confirmation/finish page.

### Administration
- Adds a `Pickup Locations` module under **Content**.
- Lets you create/edit pickup locations and assign them to sales channels.
- Configure a per-location timezone, a weekly opening schedule and special-date
  overrides (holidays/exceptions), plus contact details, geo fields and active state.

### Checkout and Order Processing
- Creates a `Pay on pickup` payment method on activate.
- Creates a `Self pick-up` shipping method via migration.
- Restricts `Pay on pickup` to the plugin shipping method.
- Validates the pickup selection server-side (cart validator): blocks checkout
  when a location is missing or the chosen time is outside the schedule.
- Persists the pickup location, time and instructions on the order via the
  `kommandhub_order_pickup_location` entity (a OneToOne order extension).
- Dispatches `pickup.order.placed` for pickup orders.

### Administration order view
- Adds a read-only `Pickup information` tab on the order detail page showing the
  selected location, address/contact, chosen date/time and instructions. Handles a
  since-deleted pickup location gracefully (keeps the historical pickup data).

### Order State and Notifications
- Adds delivery state `ready_for_pickup` and related transitions.
- Creates mail templates and Flow Builder entries for:
  - customer pickup-ready mail,
  - admin pickup-order-placed notification.
- Exposes `pickup.order.placed` and `pickup.order.ready` Flow Builder triggers, and
  `Send pickup notification to admin` (mail) and `Send pickup SMS to location`
  actions. The SMS action is an optional soft dependency on KommandhubSmsSW.

## Requirements

From plugin metadata and local setup files:
- Shopware core: `~6.7.0` (see `composer.json`)
- PHP: compatible with Shopware 6.7 (local Dockerfile uses PHP 8.3)
- Composer
- Node/npm only if you are developing frontend assets

## Installation Guide

### 1) Place the plugin
Ensure plugin code is available at:
`custom/plugins/KommandhubClickAndPickSW`

### 2) Install and activate
Run from your Shopware project root (where `bin/console` exists):

```bash
bin/console plugin:refresh
bin/console plugin:install --activate KommandhubClickAndPickSW
bin/console cache:clear
```

### 3) If already installed, update instead

```bash
bin/console plugin:refresh
bin/console plugin:update KommandhubClickAndPickSW
bin/console cache:clear
```

### 4) Rebuild storefront/admin assets when needed
If you changed plugin JS/Twig/Admin code, rebuild assets according to your environment. In this repository, a common path is:

```bash
bin/build-storefront.sh
```

If your setup uses a watcher or CI build pipeline, use your project-specific asset build commands.

## Configuration Guide

### A) Enable plugin behavior
In Administration:
1. Go to **Extensions > My Extensions** (or your plugin management area).
2. Open `Kommandhub Click and Pick` config.
3. Set:
   - `Enable pickup location selection`
   - `Show street name in pickup location selection`
   - `Show contact details in pickup location info`

These options are defined in `src/Resources/config/config.xml`.

### B) Prepare shipping and payment assignment
After install, plugin creates methods, but you still need to ensure assignment/availability in your sales channel configuration:
- Shipping method technical name: `kommandhub_self_pickup`
- Payment method technical name: `kommandhub_pay_on_pickup`

### C) Create pickup locations
In Administration, open **Content > Pickup Locations** and add entries.

Recommended minimum fields:
- Name
- Street
- Postal code
- City
- Email
- Sales channels
- Active = true

Useful optional fields:
- Phone number
- Additional address lines
- Timezone and the opening schedule (weekly hours + special-date overrides)
- Latitude/longitude
- Location code

### D) Verify sales channel mapping
A pickup location is selectable in checkout only when:
- location is active,
- location is mapped to the current sales channel.

## Usage Guide

### Customer flow
1. Customer selects `Self pick-up` shipping method in checkout.
2. Customer selects a pickup location, an available pickup time and optional notes.
3. Customer can use `Pay on pickup` (enforced to pickup shipping context).
4. Order is placed; the pickup selection is stored on the order via the
   `kommandhub_order_pickup_location` entity.

### Internal fulfillment flow
1. Process order as usual.
2. Move delivery state to `ready_for_pickup`.
3. Customer receives pickup-ready email via created flow.

## Data and Integration Notes

### Entities
- `kommandhub_pickup_location` — the location: address/contact/geo metadata and a
  many-to-many mapping to `sales_channel`, with normalized opening-hour and
  special-date (override) aggregates and an IANA timezone.
- `kommandhub_order_pickup_location` — one row per pickup order (a OneToOne order
  extension): `pickup_location_id`, chosen `pickup_time` and `comment`. This is the
  single source of truth for an order's pickup data. Deleting a location nulls the
  reference (`ON DELETE SET NULL`) and keeps the historical order data.

### Storefront routes
Controller: `SalesChannelPickupLocationController`
- list route: `frontend.kommandhub.sales-channel.pickup-locations.index`
- slots route: `frontend.kommandhub.sales-channel.pickup-locations.slots`

## Development Guide

Plugin root: `custom/plugins/KommandhubClickAndPickSW`

### Install plugin dependencies

```bash
cd custom/plugins/KommandhubClickAndPickSW
composer install
```

### Static analysis and code style

```bash
cd custom/plugins/KommandhubClickAndPickSW
./vendor/bin/phpstan analyse src -c phpstan.dist.neon --memory-limit=1G
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Tests

```bash
cd custom/plugins/KommandhubClickAndPickSW
./vendor/bin/phpunit -c phpunit.dist.xml
```

### Optional Docker workflow (plugin-local)
The plugin contains its own `docker-compose.yml`, `Dockerfile`, and `Makefile`.

```bash
cd custom/plugins/KommandhubClickAndPickSW
make up
make cs-fix analyse test
```

`make validate-plugin` runs shopware-cli store-compliance checks and `make zip`
builds a distributable package into `build/` (both need `make build` first so the
image picks up the bundled `shopware-cli`). Run `make help` for the full list.

## Troubleshooting

### Pickup locations do not appear in checkout
- Confirm plugin config enables pickup selection.
- Confirm location is active.
- Confirm location is assigned to current sales channel.
- Clear cache and rebuild storefront assets.

### Pickup info block not rendering updates
- Ensure storefront JS was rebuilt after changes.
- Confirm data attributes exist on rendered shipping method markup.

### `Pay on pickup` not available
- Ensure plugin is active.
- Ensure selected shipping method is `Self pick-up`.
- Verify payment method is active and assigned in sales channel settings.

### Mail flows not triggered
- Verify plugin migrations executed successfully.
- Check Flow Builder entries created by migration.
- Confirm state transition to `ready_for_pickup` occurs on order delivery.

## Security and Data Handling

- Uninstall keeps (but deactivates) the payment and shipping methods, so historical
  orders that reference them stay intact.
- Uninstalling with "keep user data" leaves all plugin tables in place. Without it,
  the plugin tables (`kommandhub_order_pickup_location`, the opening-hour and
  special-hour aggregates, the sales-channel mapping and `kommandhub_pickup_location`)
  are dropped child-first so foreign keys don't block removal. Orders themselves are
  never deleted.

## Version and Compatibility

- Plugin package version: `0.9.0` (first public, pre-1.0 release)
- Target Shopware core: `~6.7.0`
- License: Proprietary

## Support

- Vendor: Kommandhub Limited
- Support: https://www.kommandhub.com
- Website: https://www.kommandhub.com
