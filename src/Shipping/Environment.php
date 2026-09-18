<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

use Calmfox\InPostBundle\Repository\SettingsRepository;

/**
 * W którym trybie sklep nadaje NOWE przesyłki. Rozstrzyga panel (tabela ustawień); dopóki nikt
 * tam niczego nie zapisał — albo tabeli jeszcze nie ma, bo sklep nie puścił migracji — obowiązuje
 * `calmfox_inpost.sandbox` z konfiguracji. Przesyłki już nadane pamiętają własny tryb.
 */
final class Environment
{
    private ?bool $resolved = null;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly bool $default,
    ) {
    }

    public function isSandbox(): bool
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        try {
            $stored = $this->settings->findSettings();
        } catch (\Throwable) {
            // Brak tabeli nie może wyłożyć koszyka ani zamówienia w panelu.
            $stored = null;
        }

        return $this->resolved = $stored?->isSandbox() ?? $this->default;
    }

    public function switchTo(bool $sandbox): void
    {
        $this->settings->saveSandbox($sandbox);
        $this->resolved = $sandbox;
    }

    public function getDefault(): bool
    {
        return $this->default;
    }
}
