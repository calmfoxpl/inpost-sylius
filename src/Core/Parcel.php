<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Paczka w jednym z dwóch zapisów, które rozumie ShipX: gabaryt paczkomatowy (A/B/C jako
 * small/medium/large) albo wymiary w milimetrach z wagą w kilogramach. Kurier wymaga
 * wymiarów; paczkomat przyjmuje oba, ale szablon jest tym, co operator zna z cennika.
 */
final class Parcel
{
    public const TEMPLATES = ['small', 'medium', 'large'];

    private function __construct(
        public readonly ?string $template,
        public readonly ?int $length,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?float $weight,
    ) {
    }

    public static function template(string $template): self
    {
        if (!\in_array($template, self::TEMPLATES, true)) {
            throw new \InvalidArgumentException(sprintf('Nieznany gabaryt „%s". Dozwolone: %s.', $template, implode(', ', self::TEMPLATES)));
        }

        return new self($template, null, null, null, null);
    }

    public static function dimensions(int $lengthMm, int $widthMm, int $heightMm, float $weightKg): self
    {
        if ($lengthMm <= 0 || $widthMm <= 0 || $heightMm <= 0 || $weightKg <= 0) {
            throw new \InvalidArgumentException('Wymiary i waga paczki muszą być dodatnie.');
        }

        return new self(null, $lengthMm, $widthMm, $heightMm, $weightKg);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        if (null !== $this->template) {
            return ['template' => $this->template];
        }

        return [
            'dimensions' => ['length' => $this->length, 'width' => $this->width, 'height' => $this->height, 'unit' => 'mm'],
            'weight' => ['amount' => $this->weight, 'unit' => 'kg'],
        ];
    }
}
