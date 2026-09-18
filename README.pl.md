# calmfox/inpost-sylius

InPost (ShipX) dla Syliusa 2. Paczkomaty i kurier z jednego konta, bez zmian w encjach sklepu.

[English version](README.md)

- **Koszyk:** klient wybiera paczkomat z listy najbliższych punktów (po kodzie pocztowym, mieście albo kodzie paczkomatu). Bez mapy osadzanej z cudzej domeny, bez dodatkowego tokenu, bez webpacka.
- **Panel:** w zamówieniu, pod przesyłkami — „Nadaj w InPost" (gabaryt albo wymiary, ubezpieczenie podpowiedziane z wartości zamówienia, pobranie przy płatności przy odbiorze), status, numer nadania, etykieta PDF prosto z InPostu.
- **Przełącznik sandboxa w panelu:** Konfiguracja → InPost przełącza między kontem produkcyjnym a testowym, sprawdza połączenie i zbiera potrzebne adresy. Każda przesyłka pamięta tryb, w którym powstała.
- **Dane:** jedna tabela `calmfox_inpost_shipment` powiązana z przesyłką Syliusa. Numer nadania trafia też do pola `tracking` przesyłki, więc widzi go e-mail „wysłano" i konto klienta.

## Wymagania

PHP 8.2+, Sylius 2.x, Symfony 6.4 / 7.x, Doctrine ORM. Konto InPost z dostępem do API ShipX.

## Instalacja

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
    sandbox_api_token: '%env(INPOST_SANDBOX_API_TOKEN)%'            # opcjonalnie, do testów
    sandbox_organization_id: '%env(INPOST_SANDBOX_ORGANIZATION_ID)%'
    methods:
        inpost_point: inpost_locker_standard   # kod metody dostawy w Syliusie → usługa ShipX
        inpost: inpost_courier_standard
```

Tabela i zasoby:

```bash
bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate
bin/console assets:install
```

Na koniec w panelu Syliusa załóż metody dostawy o kodach z mapy `methods` i przypnij je do kanału. To wszystko — mapowanie encji i miejsca w szablonach paczka dopina sama (Twig Hooks).

## Konfiguracja

| Klucz | Domyślnie | Znaczenie |
|---|---|---|
| `api_token`, `organization_id` | puste | Dane konta ShipX (Manager Paczek → Moje konto → API). Jeden komplet na sklep. |
| `sandbox_api_token`, `sandbox_organization_id` | puste | Dane osobnego konta sandbox. Potrzebne tylko do testów. |
| `sandbox` | `false` | Tryb startowy. Obowiązuje, dopóki ktoś nie przełączy trybu w panelu (Konfiguracja → InPost); potem rozstrzyga panel. |
| `methods` | `inpost_point`, `inpost` | Kod metody dostawy → usługa ShipX. Usługi `inpost_locker_*` wymagają wyboru punktu. |
| `sending_method` | `dispatch_order` | Jak paczka trafia do InPostu: `dispatch_order` (odbiera kurier), `parcel_locker` (nadajesz w paczkomacie), `pop`, `any_point`, `branch`. |
| `locker_template` | `small` | Gabaryt podpowiadany przy nadaniu: `small` (A), `medium` (B), `large` (C). |
| `courier_parcel` | 400×300×150 mm, 2 kg | Wymiary i waga podpowiadane przy nadaniu kurierem. |
| `insurance.mode` | `order_total` | `order_total` — ubezpieczenie na wartość zamówienia; `none` — bez ubezpieczenia. |
| `insurance.max_amount` | `null` | Górny limit ubezpieczenia w groszach. Droższe zamówienia najlepiej odciąć regułą metody dostawy „wartość zamówienia ≤". |
| `cod_payment_methods` | `[cash_on_delivery]` | Kody metod płatności oznaczających pobranie. Pobranie = kwota zamówienia; ubezpieczenie jest podnoszone co najmniej do kwoty pobrania. |
| `points_cache_ttl` | `900` | Ile sekund trzymać wyniki wyszukiwania punktów w `cache.app`. |

## Sandbox

InPost ma pełne środowisko testowe: przesyłki dostają numer nadania i etykietę PDF, ale nikt ich nie odbiera i nic nie kosztują. To **osobne konto z własnym tokenem i ID organizacji** — załóż je w [sandboxowym Managerze Paczek](https://sandbox-manager.paczkomaty.pl), wpisz dane do `sandbox_api_token` / `sandbox_organization_id`, a potem przełącz tryb w panelu: **Konfiguracja → InPost → Przełącz na sandbox**. Na tym samym ekranie jest „Sprawdź połączenie", które powie też, gdy konto nie ma usługi używanej przez Twoje metody dostawy.

Przełączenie dotyczy tylko nowych przesyłek. Przesyłka nadana w sandboxie rozmawia z sandboxem (status, etykieta) także po powrocie na produkcję, jej testowy numer nadania nie trafia do przesyłki Syliusa, a w zamówieniu ma znaczek „Sandbox". Lista paczkomatów w koszyku zawsze pochodzi z produkcyjnego API punktów — sandbox ma te same punkty.

## Jak to działa

**Wybór punktu** jest ukrytym polem formularza kroku dostawy (`ShipmentType`), nie osobnym żądaniem AJAX. Brak punktu przy metodzie paczkomatowej to zwykły błąd formularza; nic nie blokuje przycisków w JS. Punkt jest sprawdzany w publicznym API punktów InPostu — gdy to API nie odpowiada, sklep przyjmuje kod z listy i nie zatrzymuje sprzedaży. Druga zapora (`PointSelected`, grupa `sylius_checkout_complete`) łapie przypadek, w którym Sylius sam przestawił metodę po zmianie koszyka.

**Telefon** musi być polskim numerem komórkowym — InPost wysyła na niego kod odbioru. Paczka normalizuje zapis (`+48 605-203-478` → `605203478`) i odrzuca numery, których ShipX nie przyjmie, już w kroku dostawy.

**Nadanie** jest synchroniczne: operator klika, ShipX odpowiada w sekundę, a błąd walidacji wraca w czytelnej postaci (`receiver.phone: invalid`). Numer nadania pojawia się, gdy ShipX kupi ofertę — paczka czeka na niego kilka sekund, potem zostaje przycisk „Odśwież status" i polecenie do crona:

```bash
bin/console calmfox:inpost:sync
```

**Wygląd** — arkusz `public/inpost.css` jest neutralny. Dopasuj go zmiennymi `--calmfox-inpost-border`, `--calmfox-inpost-accent`, `--calmfox-inpost-muted` albo nadpisując klasy `.calmfox-inpost__*`. Szablony: `@CalmfoxInPost/shop/point_picker.html.twig`, `@CalmfoxInPost/admin/shipments.html.twig`.

## Przydatne adresy

Tę samą listę pokazuje panel: Konfiguracja → InPost.

| | |
|---|---|
| Manager Paczek — konto produkcyjne, token API, zlecenia odbioru | https://manager.paczkomaty.pl |
| Manager Paczek Sandbox — konto testowe i token do testów | https://sandbox-manager.paczkomaty.pl |
| Dokumentacja API ShipX | https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/overview |
| Śledzenie przesyłek | https://inpost.pl/sledzenie-przesylek |
| Mapa paczkomatów | https://inpost.pl/znajdz-paczkomat |
| Kontakt z InPost | https://inpost.pl/kontakt |
| Zgłoś błąd w paczce | https://github.com/calmfoxpl/inpost-sylius/issues |

## Czego paczka nie robi

Zleceń odbioru przez kuriera, zwrotów, cenników ani mapy punktów. Pierwsze trzy operator ma w Managerze Paczek; mapa to świadoma decyzja — lista najbliższych punktów nie wymaga tokenu Geowidgetu ani ładowania skryptów InPostu na stronach sklepu.

## Testy

```bash
vendor/bin/phpunit
```

Rdzeń (`src/Core`) nie zależy od Symfony ani Syliusa. Testy klientów API używają `MockHttpClient` — żaden test nie łączy się z InPostem.

## Licencja

MIT © Calmfox
