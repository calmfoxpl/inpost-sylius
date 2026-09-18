<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Api;

use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dwa klienty ShipX — produkcyjny i sandboxowy — każdy z własnym tokenem i ID organizacji.
 * Tryb wybiera wołający: nowe nadanie bierze go z ustawień, a odświeżenie statusu i etykieta
 * z samej przesyłki, bo przesyłka utworzona w sandboxie istnieje tylko w sandboxie.
 *
 * Klient powstaje przy każdym wywołaniu: dane konta można zmienić w panelu i następne żądanie
 * ma już iść z nowymi, także w długo żyjącym procesie (worker, polecenie sync).
 */
final class ShipXClients
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CredentialsProvider $credentials,
    ) {
    }

    public function get(bool $sandbox): ShipXClient
    {
        $credentials = $this->credentials->get($sandbox);

        return new ShipXClient($this->httpClient, $credentials->token, $credentials->organizationId, $sandbox);
    }
}
