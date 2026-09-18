<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Adresy, po które operator sięga przy pracy z InPostem. Jedno miejsce, z którego czytają
 * ekran ustawień, blok w zamówieniu i README — żeby nie rozjechały się przy pierwszej zmianie
 * po stronie InPostu.
 */
final class Links
{
    public const MANAGER = 'https://manager.paczkomaty.pl';
    public const MANAGER_SANDBOX = 'https://sandbox-manager.paczkomaty.pl';
    public const API_DOCS = 'https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/overview';
    public const TRACKING = 'https://inpost.pl/sledzenie-przesylek';
    public const POINT_FINDER = 'https://inpost.pl/znajdz-paczkomat';
    public const INPOST_CONTACT = 'https://inpost.pl/kontakt';
    public const PACKAGE = 'https://github.com/calmfoxpl/inpost-sylius';
    public const PACKAGE_ISSUES = 'https://github.com/calmfoxpl/inpost-sylius/issues';

    public static function tracking(string $trackingNumber): string
    {
        return self::TRACKING.'?number='.rawurlencode($trackingNumber);
    }

    public static function manager(bool $sandbox): string
    {
        return $sandbox ? self::MANAGER_SANDBOX : self::MANAGER;
    }

    /**
     * @return list<array{key: string, url: string}> klucz tłumaczenia `calmfox_inpost.links.<key>`
     */
    public static function forOperator(): array
    {
        return [
            ['key' => 'manager', 'url' => self::MANAGER],
            ['key' => 'manager_sandbox', 'url' => self::MANAGER_SANDBOX],
            ['key' => 'api_docs', 'url' => self::API_DOCS],
            ['key' => 'tracking', 'url' => self::TRACKING],
            ['key' => 'point_finder', 'url' => self::POINT_FINDER],
            ['key' => 'inpost_contact', 'url' => self::INPOST_CONTACT],
            ['key' => 'package', 'url' => self::PACKAGE],
            ['key' => 'package_issues', 'url' => self::PACKAGE_ISSUES],
        ];
    }
}
