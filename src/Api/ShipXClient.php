<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Api;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Cztery wywołania ShipX, których potrzebuje sklep: utwórz przesyłkę, odczytaj ją,
 * pobierz etykietę, sprawdź konto. Nic więcej — zlecenia odbioru, zwroty i cenniki
 * operator ma w Managerze Paczek.
 */
final class ShipXClient
{
    public const PRODUCTION = 'https://api-shipx-pl.easypack24.net';
    public const SANDBOX = 'https://sandbox-api-shipx-pl.easypack24.net';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiToken,
        private readonly string $organizationId,
        private readonly bool $sandbox = false,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiToken) && '' !== trim($this->organizationId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {
        return $this->json('POST', sprintf('/v1/organizations/%s/shipments', rawurlencode($this->organizationId)), ['json' => $payload]);
    }

    /** @return array<string, mixed> */
    public function getShipment(string $shipmentId): array
    {
        return $this->json('GET', '/v1/shipments/'.rawurlencode($shipmentId));
    }

    /** @return array<string, mixed> */
    public function getOrganization(): array
    {
        return $this->json('GET', '/v1/organizations/'.rawurlencode($this->organizationId));
    }

    /** Etykieta jako surowy PDF; format A6 pasuje do drukarek etykiet i do kartki A4. */
    public function getLabel(string $shipmentId, string $type = 'A6'): string
    {
        $response = $this->request('GET', '/v1/shipments/'.rawurlencode($shipmentId).'/label', [
            'query' => ['format' => 'pdf', 'type' => $type],
        ]);

        try {
            return $response->getContent(false);
        } catch (HttpException $e) {
            throw new ShipXException('Nie udało się pobrać etykiety: '.$e->getMessage(), 0, [], $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $options = []): array
    {
        $response = $this->request($method, $path, $options);

        try {
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (HttpException $e) {
            throw new ShipXException('InPost zwrócił odpowiedź, której nie da się odczytać: '.$e->getMessage(), 0, [], $e);
        }

        return $data;
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        if (!$this->isConfigured()) {
            throw new ShipXException('Brak tokenu API albo identyfikatora organizacji InPost w konfiguracji (calmfox_inpost.api_token, calmfox_inpost.organization_id).');
        }

        $options['headers'] = ['Authorization' => 'Bearer '.$this->apiToken, 'Accept' => 'application/json'] + ($options['headers'] ?? []);
        $options['timeout'] ??= 15;

        try {
            $response = $this->httpClient->request($method, ($this->sandbox ? self::SANDBOX : self::PRODUCTION).$path, $options);
            $status = $response->getStatusCode();
        } catch (HttpException $e) {
            throw new ShipXException('Brak połączenia z InPost: '.$e->getMessage(), 0, [], $e);
        }

        if ($status >= 400) {
            throw ShipXException::fromResponse($status, $response->getContent(false));
        }

        return $response;
    }
}
