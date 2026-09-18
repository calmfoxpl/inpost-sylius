<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * ShipX tworzy przesyłkę asynchronicznie: po POST jest `created`, potem sam dobiera i kupuje
 * ofertę, a numer nadania pojawia się dopiero przy `confirmed`. Statusów doręczenia jest
 * kilkadziesiąt i InPost je dokłada, więc nie tłumaczymy ich wszystkich — integrację
 * interesują trzy pytania: czy etykieta już jest, czy warto jeszcze pytać, czy to koniec.
 */
final class ShipmentStatus
{
    private const BEFORE_LABEL = ['created', 'offers_prepared', 'offer_selected'];
    private const FINAL = ['delivered', 'canceled', 'returned_to_sender', 'collected_by_customer'];

    public static function hasLabel(string $status): bool
    {
        return '' !== $status && !\in_array($status, self::BEFORE_LABEL, true) && 'canceled' !== $status;
    }

    /** @return list<string> */
    public static function finalStatuses(): array
    {
        return self::FINAL;
    }

    public static function isFinal(string $status): bool
    {
        return \in_array($status, self::FINAL, true);
    }

    /** Klucz tłumaczenia; nieznane statusy pokazujemy surowo, zamiast udawać, że je rozumiemy. */
    public static function translationKey(string $status): string
    {
        return 'calmfox_inpost.status.'.$status;
    }
}
