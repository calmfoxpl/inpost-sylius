<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Controller;

use Calmfox\InPostBundle\Api\PointsClient;
use Calmfox\InPostBundle\Api\PointsUnavailableException;
use Calmfox\InPostBundle\Core\PointQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Wyszukiwarka paczkomatów dla kroku dostawy: `?q=04-761`, `?q=Warszawa`, `?q=WAW23N`. */
final class PointSearchController
{
    public function __construct(private readonly PointsClient $pointsClient)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $query = PointQuery::parse(mb_substr((string) $request->query->get('q', ''), 0, 64));
        if (null === $query) {
            return new JsonResponse(['points' => [], 'error' => 'query'], 422);
        }

        try {
            $points = $this->pointsClient->search($query);
        } catch (PointsUnavailableException) {
            return new JsonResponse(['points' => [], 'error' => 'unavailable'], 503);
        }

        $response = new JsonResponse(['points' => $points, 'kind' => $query->kind]);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
