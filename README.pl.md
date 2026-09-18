# calmfox/inpost-sylius

InPost (ShipX) dla Syliusa 2. Paczkomaty i kurier z jednego konta, bez zmian w encjach sklepu.

[English version](README.md)

- **Koszyk:** klient wybiera paczkomat na oficjalnej mapie InPostu (Geowidget v5) albo z listy najbliższych punktów (po kodzie pocztowym, mieście albo kodzie paczkomatu). Skrypt mapy ładuje się dopiero, gdy klient ją otworzy; lista działa bez żadnego tokenu. Bez webpacka.
- **Panel:** w zamówieniu, pod przesyłkami — „Nadaj w InPost" (gabaryt albo wymiary, ubezpieczenie podpowiedziane z wartości zamówienia, pobranie przy płatności przy odbiorze), status, numer nadania, etykieta PDF prosto z InPostu.
- **Ustawienia w panelu:** Konfiguracja → InPost trzyma dane konta produkcyjnego i sandboxowego (pole tokenu jest jednostronne, a token leży w bazie zaszyfrowany), przełącza między nimi, sprawdza połączenie i zbiera potrzebne adresy. Każda przesyłka pamięta tryb, w którym powstała.
- **Dane:** jedna tabela `calmfox_inpost_shipment` powiązana z przesyłką Syliusa. Numer nadania trafia też do pola `tracking` przesyłki, więc widzi go e-mail „wysłano" i konto klienta.

## Zrzuty ekranu

**Koszyk — wybór paczkomatu** (lista najbliższych punktów i przycisk mapy; wygląd z motywu sklepu):

![Wybór paczkomatu w koszyku](docs/checkout-picker.png)

**Panel — Konfiguracja → InPost** (dane kont produkcyjnego i sandbox, przełącznik trybu, test połączenia, adresy):

![Ekran ustawień InPost](docs/admin-settings.png)

**Panel — zamówienie** (nadanie, status, etykieta):

![Blok InPost w zamówieniu](docs/admin-order.png)

**Panel — metody dostawy** (ostrzeżenie z linkiem, gdy aktywny tryb nie ma danych konta):

![Ostrzeżenie na liście metod dostawy](docs/admin-alert.png)

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

`config/packages/calmfox_inpost.yaml` — wymagana jest tylko mapa metod; dane konta można zamiast tego wpisać w panelu:

```yaml
calmfox_inpost:
    # Opcjonalne. To, co zapisano w panelu (Konfiguracja → InPost), wygrywa z tymi wartościami.
    api_token: '%env(INPOST_API_TOKEN)%'
    organization_id: '%env(INPOST_ORGANIZATION_ID)%'
    sandbox_api_token: '%env(INPOST_SANDBOX_API_TOKEN)%'
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

Na koniec w panelu Syliusa załóż metody dostawy o kodach z mapy `methods`, przypnij je do kanału i wpisz dane konta w **Konfiguracja → InPost** (dopóki tego nie zrobisz, ekrany metod dostawy pokazują ostrzeżenie z linkiem). To wszystko — mapowanie encji i miejsca w szablonach paczka dopina sama (Twig Hooks).

## Konfiguracja

| Klucz | Domyślnie | Znaczenie |
|---|---|---|
| `api_token`, `organization_id` | puste | Dane konta ShipX (Manager Paczek → Moje konto → API). Zapas dla tego, co zapisano w panelu. |
| `sandbox_api_token`, `sandbox_organization_id` | puste | Dane osobnego konta sandbox. Ta sama zasada zapasu. |
| `geowidget_token`, `sandbox_geowidget_token` | puste | Tokeny mapy, zapas dla pól w panelu. Puste = sama lista. |
| `geowidget_config` | `parcelcollect` | Które punkty pokazuje mapa. |
| `sandbox` | `false` | Tryb startowy. Obowiązuje, dopóki ktoś nie przełączy trybu w panelu (Konfiguracja → InPost); potem rozstrzyga panel. |
| `methods` | `inpost_point`, `inpost` | Kod metody dostawy → usługa ShipX. Usługi `inpost_locker_*` wymagają wyboru punktu. |
| `sending_method` | `dispatch_order` | Jak paczka trafia do InPostu: `dispatch_order` (odbiera kurier), `parcel_locker` (nadajesz w paczkomacie), `pop`, `any_point`, `branch`. |
| `locker_template` | `small` | Gabaryt podpowiadany przy nadaniu: `small` (A), `medium` (B), `large` (C). |
| `courier_parcel` | 400×300×150 mm, 2 kg | Wymiary i waga podpowiadane przy nadaniu kurierem. |
| `insurance.mode` | `order_total` | `order_total` — ubezpieczenie na wartość zamówienia; `none` — bez ubezpieczenia. |
| `insurance.max_amount` | `null` | Górny limit ubezpieczenia w groszach. Droższe zamówienia najlepiej odciąć regułą metody dostawy „wartość zamówienia ≤". |
| `cod_payment_methods` | `[cash_on_delivery]` | Kody metod płatności oznaczających pobranie. Pobranie = kwota zamówienia; ubezpieczenie jest podnoszone co najmniej do kwoty pobrania. |
| `points_cache_ttl` | `900` | Ile sekund trzymać wyniki wyszukiwania punktów w `cache.app`. |

## Dane konta

Wpisuje się je w panelu, **Konfiguracja → InPost**, osobno dla produkcji i sandboxa. Pole tokenu jest **jednostronne**: token można zapisać, podmienić albo usunąć, ale żadna strona go nie pokaże — ekran mówi tylko, czy token jest i skąd pochodzi (panel / konfiguracja sklepu). W bazie token leży zaszyfrowany (libsodium secretbox) kluczem wyprowadzonym z sekretu aplikacji (`APP_SECRET`), więc nie wycieka ze zrzutami i kopiami zapasowymi. Po zmianie `APP_SECRET` zapisane tokeny przestają się odszyfrowywać i ekran pokaże ich brak — trzeba wkleić je ponownie.

Wolisz sekrety poza bazą? Zostaw pola w panelu puste i ustaw klucze konfiguracji ze zmiennych środowiskowych; są używane zawsze, gdy panel nie ma wartości. Każde pole rozstrzyga się osobno, więc ID organizacji może pochodzić z konfiguracji, a token z panelu.

## Mapa (Geowidget)

Wklej token Geowidgetu w **Konfiguracja → InPost**, a krok dostawy dostanie przycisk **„Wybierz na mapie"**, który otwiera w oknie oficjalny Geowidget v5 InPostu. Token wydaje Manager Paczek **na domenę sklepu**, osobno w produkcji i w sandboxie, a każde środowisko ma własny host widgetu — dlatego mapa idzie za trybem sklepu, a token z jednego środowiska nie działa w drugim. Bez tokenu dla bieżącego trybu przycisku po prostu nie ma i zostaje lista najbliższych punktów.

Skrypt i arkusz widgetu ładują się z InPostu dopiero po kliknięciu przycisku, nigdy na innych stronach. Token Geowidgetu jest publiczny z natury (trafia do kodu strony), więc inaczej niż token ShipX jest w panelu widoczny i leży w bazie jawnie. Jeśli sklep wysyła Content-Security-Policy, dopuść `geowidget.inpost.pl` (a dla sandboxa `sandbox-easy-geowidget-sdk.easypack24.net`) dla skryptów, stylów i połączeń. Klucz `geowidget_config` wybiera zestaw punktów: `parcelcollect` (domyślnie), `parcelcollect247`, `parcelcollectpayment`, `parcelsend`.

## Sandbox

InPost ma pełne środowisko testowe: przesyłki dostają numer nadania i etykietę PDF, ale nikt ich nie odbiera i nic nie kosztują. To **osobne konto z własnym tokenem i ID organizacji** — załóż je w [sandboxowym Managerze Paczek](https://sandbox-manager.paczkomaty.pl), zapisz jego token i ID organizacji w karcie Sandbox na ekranie **Konfiguracja → InPost**, a potem kliknij **Przełącz na sandbox**. Na tym samym ekranie jest „Sprawdź połączenie", które powie też, gdy konto nie ma usługi używanej przez Twoje metody dostawy.

Przełączenie dotyczy tylko nowych przesyłek. Przesyłka nadana w sandboxie rozmawia z sandboxem (status, etykieta) także po powrocie na produkcję, jej testowy numer nadania nie trafia do przesyłki Syliusa, a w zamówieniu ma znaczek „Sandbox". Lista paczkomatów w koszyku zawsze pochodzi z produkcyjnego API punktów — sandbox ma te same punkty.

## Jak to działa

**Wybór punktu** jest ukrytym polem formularza kroku dostawy (`ShipmentType`), nie osobnym żądaniem AJAX. Brak punktu przy metodzie paczkomatowej to zwykły błąd formularza; nic nie blokuje przycisków w JS. Punkt jest sprawdzany w publicznym API punktów InPostu — gdy to API nie odpowiada, sklep przyjmuje kod z listy i nie zatrzymuje sprzedaży. Druga zapora (`PointSelected`, grupa `sylius_checkout_complete`) łapie przypadek, w którym Sylius sam przestawił metodę po zmianie koszyka.

**Telefon** musi być polskim numerem komórkowym — InPost wysyła na niego kod odbioru. Paczka normalizuje zapis (`+48 605-203-478` → `605203478`) i odrzuca numery, których ShipX nie przyjmie, już w kroku dostawy.

**Nadanie** jest synchroniczne: operator klika, ShipX odpowiada w sekundę, a błąd walidacji wraca w czytelnej postaci (`receiver.phone: invalid`). Numer nadania pojawia się, gdy ShipX kupi ofertę — paczka czeka na niego kilka sekund, potem zostaje przycisk „Odśwież status" i polecenie do crona:

```bash
bin/console calmfox:inpost:sync
```

Stan integracji z wiersza poleceń — tryb, skąd pochodzą dane obu kont, czy ShipX je przyjmuje i czy konto ma usługi używane przez metody dostawy (bez wypisywania sekretów):

```bash
bin/console calmfox:inpost:status
```

**Wygląd** — arkusz `public/inpost.css` jest neutralny. Dopasuj go zmiennymi `--calmfox-inpost-border`, `--calmfox-inpost-accent`, `--calmfox-inpost-muted` albo nadpisując klasy `.calmfox-inpost__*`. Szablony: `@CalmfoxInPost/shop/point_picker.html.twig`, `@CalmfoxInPost/admin/shipments.html.twig`.

## Przydatne adresy

Tę samą listę pokazuje panel: Konfiguracja → InPost.

| | |
|---|---|
| Manager Paczek — konto produkcyjne, token API, zlecenia odbioru | https://manager.paczkomaty.pl |
| Manager Paczek Sandbox — konto testowe i token do testów | https://sandbox-manager.paczkomaty.pl |
| Dokumentacja API ShipX | https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/overview |
| Dokumentacja Geowidgetu (mapa) — generowanie tokenu na domenę | https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/50069505/Geowidget+v5 |
| Śledzenie przesyłek | https://inpost.pl/sledzenie-przesylek |
| Mapa paczkomatów | https://inpost.pl/znajdz-paczkomat |
| Kontakt z InPost | https://inpost.pl/kontakt |
| Zgłoś błąd w paczce | https://github.com/calmfoxpl/inpost-sylius/issues |

## Czego paczka nie robi

Zleceń odbioru przez kuriera, zwrotów ani cenników — te operator ma w Managerze Paczek.

## Testy

```bash
vendor/bin/phpunit
```

Rdzeń (`src/Core`) nie zależy od Symfony ani Syliusa. Testy klientów API używają `MockHttpClient` — żaden test nie łączy się z InPostem.

## Licencja

MIT © Calmfox
