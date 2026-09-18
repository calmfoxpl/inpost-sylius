<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Klient wpisuje w jedno pole, co mu wygodnie: kod pocztowy, miasto albo od razu kod
 * paczkomatu, który zna z aplikacji. Rozpoznajemy, co to jest, i zamieniamy na parametry
 * publicznego API punktów.
 */
final class PointQuery
{
    public const KIND_POSTCODE = 'postcode';
    public const KIND_POINT = 'point';
    public const KIND_CITY = 'city';

    private function __construct(
        public readonly string $kind,
        public readonly string $value,
    ) {
    }

    public static function parse(string $input): ?self
    {
        $input = trim(preg_replace('/\s+/u', ' ', $input) ?? '');

        if (1 === preg_match('/^(\d{2})[\s-]?(\d{3})$/', $input, $m)) {
            return new self(self::KIND_POSTCODE, $m[1].'-'.$m[2]);
        }

        // Kody punktów to wielkie litery z cyframi („WAW23N", „KRA01APP", „POP-WAW12").
        $upper = mb_strtoupper($input);
        if (1 === preg_match('/^(?=.*\d)[A-Z0-9-]{4,}$/', $upper)) {
            return new self(self::KIND_POINT, $upper);
        }

        if (mb_strlen($input) >= 3 && 1 === preg_match('/^[\p{L} .\'-]+$/u', $input)) {
            return new self(self::KIND_CITY, $input);
        }

        return null;
    }

    /** @return array<string, string> */
    public function toApiParameters(): array
    {
        return match ($this->kind) {
            self::KIND_POSTCODE => ['relative_post_code' => $this->value],
            self::KIND_POINT => ['name' => $this->value],
            default => ['city' => $this->value],
        };
    }
}
