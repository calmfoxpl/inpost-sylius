<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Nazwy usług ShipX są otwartym zbiorem (InPost dokłada nowe), więc nie zamykamy ich w enumie.
 * Jedyne rozróżnienie, które zmienia zachowanie integracji, to „do punktu" kontra „pod adres":
 * przesyłka do punktu wymaga wybranego paczkomatu i szablonu gabarytu, kurierska — adresu
 * i wymiarów.
 */
final class Service
{
    public const LOCKER_STANDARD = 'inpost_locker_standard';
    public const COURIER_STANDARD = 'inpost_courier_standard';

    public static function requiresPoint(string $service): bool
    {
        return str_starts_with($service, 'inpost_locker');
    }
}
