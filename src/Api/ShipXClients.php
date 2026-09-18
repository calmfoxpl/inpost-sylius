<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Api;

/**
 * Dwa klienty ShipX — produkcyjny i sandboxowy — każdy z własnym tokenem i ID organizacji.
 * Tryb wybiera wołający: nowe nadanie bierze go z ustawień, a odświeżenie statusu i etykieta
 * z samej przesyłki, bo przesyłka utworzona w sandboxie istnieje tylko w sandboxie.
 */
final class ShipXClients
{
    public function __construct(
        private readonly ShipXClient $production,
        private readonly ShipXClient $sandbox,
    ) {
    }

    public function get(bool $sandbox): ShipXClient
    {
        return $sandbox ? $this->sandbox : $this->production;
    }
}
