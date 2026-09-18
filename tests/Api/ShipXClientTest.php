<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Api;

use Calmfox\InPostBundle\Api\ShipXClient;
use Calmfox\InPostBundle\Api\ShipXException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ShipXClientTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MockHttpClient::class)) {
            self::markTestSkipped('Wymaga symfony/http-client.');
        }
    }

    public function testCreateShipmentTalksToTheOrganizationEndpointWithBearerToken(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['method' => $method, 'url' => $url, 'headers' => $options['headers'], 'body' => $options['body']];

            return new MockResponse('{"id": 1234567, "status": "created"}', ['http_code' => 201]);
        });

        $created = (new ShipXClient($http, 'tok', '98765'))->createShipment(['service' => 'inpost_locker_standard']);

        self::assertSame(1234567, $created['id']);
        self::assertSame('POST', $seen['method']);
        self::assertSame('https://api-shipx-pl.easypack24.net/v1/organizations/98765/shipments', $seen['url']);
        self::assertContains('Authorization: Bearer tok', $seen['headers']);
        self::assertSame('{"service":"inpost_locker_standard"}', $seen['body']);
    }

    public function testSandboxUsesSandboxHost(): void
    {
        $url = '';
        $http = new MockHttpClient(function (string $method, string $requested) use (&$url): MockResponse {
            $url = $requested;

            return new MockResponse('{"id": 1}');
        });

        (new ShipXClient($http, 'tok', '1', true))->getShipment('55');

        self::assertSame('https://sandbox-api-shipx-pl.easypack24.net/v1/shipments/55', $url);
    }

    public function testErrorResponseBecomesOperatorMessage(): void
    {
        $http = new MockHttpClient(new MockResponse('{"status":401,"error":"token_invalid","message":"Token is missing or invalid."}', ['http_code' => 401]));

        $this->expectException(ShipXException::class);
        $this->expectExceptionMessage('odrzucił token');

        (new ShipXClient($http, 'tok', '1'))->getOrganization();
    }

    public function testMissingCredentialsNeverReachTheNetwork(): void
    {
        $http = new MockHttpClient(static function (): never { self::fail('Nie powinno być żądania HTTP.'); });
        $client = new ShipXClient($http, '', '');

        self::assertFalse($client->isConfigured());
        $this->expectException(ShipXException::class);
        $client->getOrganization();
    }

    public function testLabelReturnsRawPdf(): void
    {
        $http = new MockHttpClient(new MockResponse('%PDF-1.4 fake', ['http_code' => 200]));

        self::assertSame('%PDF-1.4 fake', (new ShipXClient($http, 'tok', '1'))->getLabel('55'));
    }
}
