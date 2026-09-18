<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Shipping;

use Calmfox\InPostBundle\Api\ShipXClient;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Shipping\Dispatcher;
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

    private function dispatcher(MockHttpClient $http, ?\Closure $sleep = null): Dispatcher
    {
        return new Dispatcher(
            new ShipXClient($http, 'tok', '98765'),
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
