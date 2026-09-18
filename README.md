# calmfox/inpost-sylius

InPost (ShipX) for Sylius 2. Parcel lockers and courier from a single account, with no changes to your shop's entities.

[Wersja polska](README.pl.md)

- **Checkout:** the customer picks a parcel locker from a list of the nearest points (by postcode, city or locker code). No third-party map embed, no extra token, no webpack.
- **Admin:** on the order page, below the shipments — "Dispatch with InPost" (locker size or parcel dimensions, insurance suggested from the order total, cash on delivery when the order is paid on delivery), status, tracking number and the label PDF straight from InPost.
- **Data:** one table, `calmfox_inpost_shipment`, linked to the Sylius shipment. The tracking number is also copied to the shipment's `tracking` field, so the "shipped" e-mail and the customer account see it.

## Requirements

PHP 8.2+, Sylius 2.x, Symfony 6.4 / 7.x, Doctrine ORM. An InPost account with ShipX API access.

## Installation

```bash
composer require calmfox/inpost-sylius
```

`config/bundles.php`:

```php
Calmfox\InPostBundle\CalmfoxInPostBundle::class => ['all' => true],
```

`config/routes/calmfox_inpost.yaml`:

```yaml
calmfox_inpost_shop:
    resource: '@CalmfoxInPostBundle/config/routes/shop.yaml'

calmfox_inpost_admin:
    resource: '@CalmfoxInPostBundle/config/routes/admin.yaml'
    prefix: '/%sylius_admin.path_name%'
```

`config/packages/calmfox_inpost.yaml`:

```yaml
calmfox_inpost:
    api_token: '%env(INPOST_API_TOKEN)%'
    organization_id: '%env(INPOST_ORGANIZATION_ID)%'
    methods:
        inpost_point: inpost_locker_standard   # Sylius shipping method code → ShipX service
        inpost: inpost_courier_standard
```

Table and assets:

```bash
bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
bin/console assets:install
```

Finally, create shipping methods in the Sylius admin with the codes from the `methods` map and enable them for your channel. That is all — entity mapping and template placement (Twig Hooks) are wired by the bundle itself.

## Configuration

| Key | Default | Meaning |
|---|---|---|
| `api_token`, `organization_id` | empty | ShipX account credentials (Parcel Manager → My account → API). One set per shop. |
| `sandbox` | `false` | `true` targets `sandbox-api-shipx-pl.easypack24.net` (the sandbox token is a separate one). |
| `methods` | `inpost_point`, `inpost` | Shipping method code → ShipX service. `inpost_locker_*` services require a pickup point. |
| `sending_method` | `dispatch_order` | How the parcel reaches InPost: `dispatch_order` (courier pickup), `parcel_locker` (you drop it at a locker), `pop`, `any_point`, `branch`. |
| `locker_template` | `small` | Size suggested when dispatching: `small` (A), `medium` (B), `large` (C). |
| `courier_parcel` | 400×300×150 mm, 2 kg | Dimensions and weight suggested for courier shipments. |
| `insurance.mode` | `order_total` | `order_total` — insure for the order value; `none` — no insurance. |
| `insurance.max_amount` | `null` | Insurance cap in minor units. Cut off more expensive orders with the built-in "order total ≤" shipping method rule. |
| `cod_payment_methods` | `[cash_on_delivery]` | Payment method codes that mean cash on delivery. COD equals the order total; insurance is raised to at least the COD amount. |
| `points_cache_ttl` | `900` | Seconds to keep point search results in `cache.app`. |

## How it works

**Point selection** is a hidden field of the shipping step form (`ShipmentType`), not a separate AJAX call. A missing point on a locker method is a regular form error; nothing disables buttons in JavaScript. The point is verified against InPost's public points API — if that API is down, the shop accepts the code from the list and keeps selling. A second guard (`PointSelected`, group `sylius_checkout_complete`) covers the case where Sylius switched the method on its own after a cart change.

**Phone** must be a Polish mobile number — InPost sends the pickup code to it. The bundle normalizes the notation (`+48 605-203-478` → `605203478`) and rejects numbers ShipX would refuse already at the shipping step.

**Dispatch** is synchronous: the operator clicks, ShipX answers within a second, and validation errors come back readable (`receiver.phone: invalid`). The tracking number appears once ShipX buys the offer — the bundle waits a few seconds for it; after that there is a "Refresh status" button and a cron-friendly command:

```bash
bin/console calmfox:inpost:sync
```

**Look** — `public/inpost.css` is neutral. Adjust it with `--calmfox-inpost-border`, `--calmfox-inpost-accent`, `--calmfox-inpost-muted`, or override the `.calmfox-inpost__*` classes. Templates: `@CalmfoxInPost/shop/point_picker.html.twig`, `@CalmfoxInPost/admin/shipments.html.twig`. Translations ship in Polish and English.

## What it does not do

Courier pickup orders, returns, price lists, or a map of points. The first three live in InPost's Parcel Manager; the missing map is deliberate — a list of the nearest points needs no Geowidget token and loads no InPost scripts on your shop pages.

## Tests

```bash
vendor/bin/phpunit
```

The core (`src/Core`) does not depend on Symfony or Sylius. API client tests use `MockHttpClient` — no test talks to InPost.

## License

MIT © Calmfox
