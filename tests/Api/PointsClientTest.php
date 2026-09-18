<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Api;

use Calmfox\InPostBundle\Api\PointsClient;
use Calmfox\InPostBundle\Api\PointsUnavailableException;
use Calmfox\InPostBundle\Core\PointQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PointsClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MockHttpClient::class)) {
            self::markTestSkipped('Wymaga symfony/http-client.');
        }
    }

    public function testSearchAsksForOperatingLockersNearPostcode(): void
    {
        $url = '';
        $http = new MockHttpClient(function (string $method, string $requested) use (&$url): MockResponse {
            $url = $requested;

            return new MockResponse((string) json_encode(['items' => [
                ['name' => 'WAW23N', 'status' => 'Operating', 'distance' => 48, 'address' => ['line1' => 'Zwoleńska 59', 'line2' => '04-761 Warszawa']],
                ['name' => 'WAW01X', 'status' => 'Disabled', 'address' => ['line1' => 'Inna 1', 'line2' => '00-001 Warszawa']],
            ]]));
        });

        $query = PointQuery::parse('04-761');
        self::assertNotNull($query);
        $points = (new PointsClient($http))->search($query);

        self::assertCount(1, $points, 'wyłączony punkt nie trafia na listę');
        self::assertSame('WAW23N', $points[0]->name);
        self::assertStringContainsString('relative_post_code=04-761', $url);
        self::assertStringContainsString('type=parcel_locker', $url);
        self::assertStringContainsString('status=Operating', $url);
    }

    public function testFindReturnsNullFor404(): void
    {
        $http = new MockHttpClient(new MockResponse('{"status":404,"key":"point_not_found"}', ['http_code' => 404]));

        self::assertNull((new PointsClient($http))->find('NIEMA999'));
    }

    public function testOutageIsNotTheSameAsMissingPoint(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        $this->expectException(PointsUnavailableException::class);
        (new PointsClient($http))->find('WAW23N');
    }
}
