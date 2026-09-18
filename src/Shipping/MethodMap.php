<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

use Calmfox\InPostBundle\Core\Service;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;

/**
 * Które metody dostawy sklepu są InPostem i jaką usługą ShipX. Źródłem jest konfiguracja
 * (`calmfox_inpost.methods`: kod metody → usługa), więc dodanie np. paczki ekonomicznej
 * to nowa metoda w panelu i jedna linia w YAML-u, bez drugiego kompletu danych konta.
 */
final class MethodMap
{
    /** @param array<string, string> $services kod metody dostawy → usługa ShipX */
    public function __construct(private readonly array $services)
    {
    }

    public function serviceFor(?ShippingMethodInterface $method): ?string
    {
        $code = $method?->getCode();

        return null === $code ? null : ($this->services[$code] ?? null);
    }

    public function requiresPoint(?ShippingMethodInterface $method): bool
    {
        $service = $this->serviceFor($method);

        return null !== $service && Service::requiresPoint($service);
    }

    /** @return list<string> kody metod, przy których klient wybiera punkt */
    public function pointMethodCodes(): array
    {
        return array_keys(array_filter($this->services, static fn (string $service): bool => Service::requiresPoint($service)));
    }
}
