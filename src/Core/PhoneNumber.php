<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * ShipX przyjmuje wyłącznie polski numer komórkowy jako 9 cyfr, bez prefiksu i separatorów.
 * Klienci wpisują go na wszystkie sposoby („+48 605-203-478", „0048605203478"), więc
 * sprowadzamy zapis do tej postaci albo mówimy wprost, że się nie da.
 */
final class PhoneNumber
{
    public static function normalize(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0048')) {
            $digits = substr($digits, 4);
        } elseif (11 === \strlen($digits) && str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        }

        return 1 === preg_match('/^[4-8]\d{8}$/', $digits) ? $digits : null;
    }
}
