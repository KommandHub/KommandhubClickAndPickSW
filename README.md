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
- Shows location details (address, open days/hours, optional contact details).
- Persists selected pickup location in the sales channel context.

### Administration
- Adds a `Pickup Locations` module under **Content**.
- Lets you create/edit pickup locations and assign them to sales channels.
- Supports open days (`openDays`), opening/closing hours, contact details, geo fields, and active state.

### Checkout and Order Processing
- Creates a `Pay on pickup` payment method on install.
- Creates a `Self pick-up` shipping method via migration.
- Restricts `Pay on pickup` to the plugin shipping method.
- Saves selected pickup location ID into order custom fields.
- Dispatches `pickup.order.placed` for pickup orders.

### Order State and Notifications
- Adds delivery state `ready_for_pickup` and related transitions.
- Creates mail templates and flow entries for:
  - customer pickup-ready mail,
  - admin pickup-order-placed notification.

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
- Open days (array of weekday keys, e.g. `monday`, `tuesday`)
- Opening/closing hours
- Latitude/longitude
- Location code

### D) Verify sales channel mapping
A pickup location is selectable in checkout only when:
- location is active,
- location is mapped to the current sales channel.

## Usage Guide

### Customer flow
1. Customer selects `Self pick-up` shipping method in checkout.
2. Customer selects a pickup location.
3. Customer can use `Pay on pickup` (enforced to pickup shipping context).
4. Order is placed with selected pickup location persisted in custom fields.

### Internal fulfillment flow
1. Process order as usual.
2. Move delivery state to `ready_for_pickup`.
3. Customer receives pickup-ready email via created flow.

## Data and Integration Notes

### Custom entity
Main entity: `kommandhub_pickup_location`
- includes address/contact/opening metadata,
- includes `open_days` JSON field,
- many-to-many mapping to `sales_channel`.

### Order custom field
The selected location ID is saved to order custom fields using key:
`kommandhub_pickup_location_id`

### Storefront routes
Controller: `SalesChannelPickupLocationController`
- list route: `frontend.kommandhub.sales-channel.pickup-locations.index`
- details route: `frontend.kommandhub.sales-channel.pickup-locations.show`

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
make up-quick
make test
```

Note: current `Makefile` default `CONTAINER_NAME` differs from `docker-compose.yml` container name. If needed, override it:

```bash
cd custom/plugins/KommandhubClickAndPickSW
make CONTAINER_NAME=kommandhub-click-and-pick-shopware test
```

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

- Uninstall behavior keeps payment method record but deactivates it to avoid historical order integrity issues.
- If uninstall is executed without keeping user data, pickup location tables are dropped.

## Version and Compatibility

- Plugin package version: `1.0.0`
- Target Shopware core: `~6.7.0`
- License: Proprietary

## Support

- Vendor: Kommandhub Limited
- Support: https://www.kommandhub.com
- Website: https://www.kommandhub.com
