<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Api;

use Calmfox\InPostBundle\Core\Point;
use Calmfox\InPostBundle\Core\PointQuery;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Publiczne API punktów InPostu — bez tokenu i bez mapy osadzanej z cudzej domeny.
 * Wyniki trzymamy krótko w pamięci podręcznej: sieć paczkomatów nie zmienia się co minutę,
 * a pole wyszukiwania w koszyku nie powinno zamieniać sklepu w przekaźnik zapytań do InPostu.
 */
final class PointsClient
{
    public const ENDPOINT = 'https://api-pl-points.easypack24.net/v1/points';

    private const FIELDS = 'name,address,location_description,opening_hours,status,distance';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly int $cacheTtl = 900,
    ) {
    }

    /**
     * @return list<Point> działające paczkomaty, dla kodu pocztowego od najbliższego
     *
     * @throws PointsUnavailableException gdy InPost nie odpowiada
     */
    public function search(PointQuery $query, int $limit = 8): array
    {
        $parameters = $query->toApiParameters() + [
            'type' => 'parcel_locker',
            'status' => 'Operating',
            'per_page' => (string) $limit,
            'fields' => self::FIELDS,
        ];

        $items = $this->cached('search.'.md5(serialize($parameters)), function () use ($parameters): array {
            $data = $this->get(self::ENDPOINT, $parameters);

            return \is_array($data['items'] ?? null) ? $data['items'] : [];
        });

        $points = [];
        foreach ($items as $item) {
            $point = \is_array($item) ? Point::fromApi($item) : null;
            if (null !== $point && $point->operating) {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * @return Point|null null, gdy takiego punktu nie ma
     *
     * @throws PointsUnavailableException gdy InPost nie odpowiada — wołający decyduje, czy to blokuje zakup
     */
    public function find(string $name): ?Point
    {
        $item = $this->cached('point.'.md5($name), function () use ($name): array {
            return $this->get(self::ENDPOINT.'/'.rawurlencode($name), ['fields' => self::FIELDS]) ?? [];
        });

        return [] === $item ? null : Point::fromApi($item);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>|null null dla 404
     */
    private function get(string $url, array $query): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['query' => $query, 'timeout' => 6, 'headers' => ['Accept' => 'application/json']]);
            $status = $response->getStatusCode();
            if (404 === $status) {
                return null;
            }
            if ($status >= 400) {
                throw new PointsUnavailableException(sprintf('API punktów InPost odpowiedziało kodem %d.', $status));
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);

            return $data;
        } catch (HttpException $e) {
            throw new PointsUnavailableException('API punktów InPost nie odpowiada: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param callable(): array<array-key, mixed> $load
     *
     * @return array<array-key, mixed>
     */
    private function cached(string $key, callable $load): array
    {
        if (null === $this->cache) {
            return $load();
        }

        $item = $this->cache->getItem('calmfox_inpost.'.$key);
        if ($item->isHit() && \is_array($item->get())) {
            return $item->get();
        }

        $value = $load();
        $this->cache->save($item->set($value)->expiresAfter($this->cacheTtl));

        return $value;
    }
}
