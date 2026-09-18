<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/** Punkt odbioru w zakresie, w jakim pokazujemy go klientowi i operatorowi. */
final class Point implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $address,
        public readonly ?string $description = null,
        public readonly ?string $openingHours = null,
        public readonly ?int $distance = null,
        public readonly bool $operating = true,
    ) {
    }

    /** @param array<string, mixed> $item element `items` z odpowiedzi API punktów */
    public static function fromApi(array $item): ?self
    {
        $name = $item['name'] ?? null;
        if (!\is_string($name) || '' === $name) {
            return null;
        }

        $address = $item['address'] ?? [];
        $lines = array_filter([
            \is_array($address) ? ($address['line1'] ?? null) : null,
            \is_array($address) ? ($address['line2'] ?? null) : null,
        ], static fn ($line): bool => \is_string($line) && '' !== $line);

        $distance = $item['distance'] ?? null;

        return new self(
            $name,
            implode(', ', $lines),
            self::text($item['location_description'] ?? null),
            self::text($item['opening_hours'] ?? null),
            is_numeric($distance) ? (int) $distance : null,
            'Operating' === ($item['status'] ?? 'Operating'),
        );
    }

    /** Zapis, który trafia do zamówienia i na ekran: „WAW23N — Zwoleńska 59, 04-761 Warszawa". */
    public function label(): string
    {
        return '' === $this->address ? $this->name : $this->name.' — '.$this->address;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'description' => $this->description,
            'openingHours' => $this->openingHours,
            'distance' => $this->distance,
        ];
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
