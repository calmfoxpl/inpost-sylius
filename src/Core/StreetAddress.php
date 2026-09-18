<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Sylius trzyma ulicę z numerem w jednym polu, ShipX chce ich osobno. Dzielimy po ostatnim
 * członie zaczynającym się od cyfry („Zwoleńska 65A", „al. Jana Pawła II 12/4 m. 3",
 * „3 Maja 5"). Gdy numeru nie da się wskazać (wieś bez ulic: „Kowalewo"), całość idzie
 * jako numer budynku — tak adresuje się w Polsce miejscowości bez nazw ulic.
 */
final class StreetAddress
{
    private function __construct(
        public readonly string $street,
        public readonly string $buildingNumber,
    ) {
    }

    public static function fromLine(string $line): self
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

        // Numer to końcowy ciąg członów „numerowych": zaczynających się od cyfry albo będących
        // oznaczeniem lokalu (m., lok.). Idziemy od końca, żeby „3 Maja 5" nie oddało „3" jako numeru.
        $tokens = explode(' ', str_replace(',', ' ', $line));
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => '' !== $t));
        $start = \count($tokens);
        while ($start > 1 && 1 === preg_match('/^(\d[\w\/\-\.]*|m\.?|lok\.?|\/)$/iu', $tokens[$start - 1])) {
            --$start;
        }
        while ($start < \count($tokens) && 1 !== preg_match('/^\d/', $tokens[$start])) {
            ++$start;
        }

        if ($start < \count($tokens)) {
            return new self(implode(' ', \array_slice($tokens, 0, $start)), implode(' ', \array_slice($tokens, $start)));
        }

        return new self('', $line);
    }
}
