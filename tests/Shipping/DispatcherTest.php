<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Shipping;

use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\SecretBox;
use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Entity\Settings;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use Calmfox\InPostBundle\Shipping\Dispatcher;
use Calmfox\InPostBundle\Shipping\Environment;
use Calmfox\InPostBundle\Shipping\ShipmentRequestFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\Shipment;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MockHttpClient::class) || !class_exists(Order::class)) {
            self::markTestSkipped('Wymaga symfony/http-client i sylius/sylius.');
        }
    }

    public function testDispatchCreatesShipmentWaitsForTrackingAndCopiesItToSylius(): void
    {
        $requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options['body'] ?? null];

            return match (\count($requests)) {
                1 => new MockResponse('{"id": 777, "status": "created", "tracking_number": null}', ['http_code' => 201]),
                2 => new MockResponse('{"id": 777, "status": "offer_selected", "tracking_number": null}'),
                default => new MockResponse('{"id": 777, "status": "confirmed", "tracking_number": "620999000000000000000001"}'),
            };
        });

        $slept = 0;
        $shipment = $this->shipment(codMethod: true);
        $dispatcher = $this->dispatcher($http, function (int $s) use (&$slept): void { $slept += $s; });

        $dispatcher->dispatch($shipment, Parcel::template('medium'));

        self::assertSame('777', $shipment->getShipxId());
        self::assertSame('confirmed', $shipment->getStatus());
        self::assertSame('620999000000000000000001', $shipment->getTrackingNumber());
        self::assertSame('620999000000000000000001', $shipment->getShipment()->getTracking());
        self::assertSame(2, $slept, 'dwa odpytania, po sekundzie przerwy');

        $payload = json_decode((string) $requests[0][2], true);
        self::assertSame('WAW23N', $payload['custom_attributes']['target_point']);
        self::assertSame([['template' => 'medium']], $payload['parcels']);
        self::assertSame(4199.0, $payload['cod']['amount'], 'płatność przy odbiorze = pobranie na kwotę zamówienia');
        self::assertSame(4199.0, $payload['insurance']['amount']);
    }

    public function testShipXErrorIsStoredOnTheShipmentForTheOperator(): void
    {
        $http = new MockHttpClient(new MockResponse('{"status":400,"error":"validation_failed","message":"Check details","details":{"custom_attributes":[{"target_point":["does_not_exist"]}]}}', ['http_code' => 400]));
        $shipment = $this->shipment();

        try {
            $this->dispatcher($http)->dispatch($shipment, Parcel::template('small'));
            self::fail('Oczekiwano wyjątku.');
        } catch (ShipXException) {
        }

        self::assertFalse($shipment->isDispatched());
        self::assertStringContainsString('custom_attributes.target_point: does_not_exist', (string) $shipment->getLastError());
    }

    public function testAlreadyDispatchedShipmentIsNotSentTwice(): void
    {
        $http = new MockHttpClient(static function (): never { self::fail('Nie powinno być żądania HTTP.'); });
        $shipment = $this->shipment();
        $shipment->markDispatched('777', 'confirmed');

        $this->expectExceptionMessage('już nadana');
        $this->dispatcher($http)->dispatch($shipment, Parcel::template('small'));
    }

    public function testSandboxModeUsesSandboxAccountAndKeepsTestTrackingAwayFromTheCustomer(): void
    {
        $urls = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$urls): MockResponse {
            $urls[] = [$url, implode(' ', $options['headers'])];

            return new MockResponse('{"id": 5, "status": "confirmed", "tracking_number": "520000000000000000000009"}', ['http_code' => 201]);
        });
        $shipment = $this->shipment();

        $this->dispatcher($http, null, true)->dispatch($shipment, Parcel::template('small'));

        self::assertTrue($shipment->isSandbox());
        self::assertStringStartsWith('https://sandbox-api-shipx-pl.easypack24.net/v1/organizations/111/', $urls[0][0]);
        self::assertStringContainsString('Bearer sandbox-tok', $urls[0][1]);
        self::assertSame('520000000000000000000009', $shipment->getTrackingNumber());
        self::assertNull($shipment->getShipment()->getTracking(), 'numer testowy nie trafia do przesyłki Syliusa ani do klienta');
    }

    public function testRefreshFollowsTheShipmentNotTheCurrentMode(): void
    {
        $url = '';
        $http = new MockHttpClient(function (string $method, string $requested) use (&$url): MockResponse {
            $url = $requested;

            return new MockResponse('{"id": 5, "status": "delivered", "tracking_number": "52"}');
        });
        $shipment = $this->shipment();
        $shipment->markDispatched('5', 'confirmed', true);

        // Sklep wrócił już na produkcję, ale ta przesyłka powstała w sandboxie.
        $this->dispatcher($http, null, false)->refresh($shipment);

        self::assertStringStartsWith('https://sandbox-api-shipx-pl.easypack24.net/v1/shipments/5', $url);
        self::assertSame('delivered', $shipment->getStatus());
    }

    private function dispatcher(MockHttpClient $http, ?\Closure $sleep = null, bool $sandbox = false): Dispatcher
    {
        $settings = $this->createStub(SettingsRepository::class);
        $settings->method('findSettings')->willReturn(new Settings($sandbox));

        return new Dispatcher(
            new ShipXClients($http, new CredentialsProvider(
                $settings,
                new SecretBox('test-secret'),
                ['token' => 'tok', 'organization_id' => '98765'],
                ['token' => 'sandbox-tok', 'organization_id' => '111'],
            )),
            new Environment($settings, false),
            new ShipmentRequestFactory(new InsurancePolicy(), ['cash_on_delivery'], 'dispatch_order'),
            $this->createStub(EntityManagerInterface::class),
            4,
            $sleep ?? static function (int $s): void {},
        );
    }

    private function shipment(bool $codMethod = false): InPostShipment
    {
        $address = new Address();
        $address->setFirstName('Jan');
        $address->setLastName('Kowalski');
        $address->setStreet('Zwoleńska 65A');
        $address->setCity('Warszawa');
        $address->setPostcode('04-761');
        $address->setCountryCode('PL');
        $address->setPhoneNumber('+48 605 203 478');

        $customer = new Customer();
        $customer->setEmail('jan@example.com');

        $order = new class() extends Order {
            public function getTotal(): int
            {
                return 419900;
            }
        };
        $order->setNumber('000000042');
        $order->setCurrencyCode('PLN');
        $order->setCustomer($customer);
        $order->setShippingAddress($address);

        if ($codMethod) {
            $method = new PaymentMethod();
            $method->setCode('cash_on_delivery');
            $payment = new Payment();
            $payment->setMethod($method);
            $payment->setState(Payment::STATE_NEW);
            $order->addPayment($payment);
        }

        $shipment = new Shipment();
        $order->addShipment($shipment);

        $inPost = new InPostShipment($shipment, Service::LOCKER_STANDARD);
        $inPost->setTargetPoint('WAW23N', 'Zwoleńska 59, 04-761 Warszawa');

        return $inPost;
    }
}
