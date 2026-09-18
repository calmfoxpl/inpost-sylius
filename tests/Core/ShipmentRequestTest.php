<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\InvalidShipmentException;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Core\ShipmentRequest;
use PHPUnit\Framework\TestCase;

final class ShipmentRequestTest extends TestCase
{
    public function testLockerShipment(): void
    {
        $payload = $this->request(service: Service::LOCKER_STANDARD, targetPoint: 'WAW23N', insuranceAmount: 419900)->toPayload();

        self::assertSame('inpost_locker_standard', $payload['service']);
        self::assertSame('000000042', $payload['reference']);
        self::assertSame('605203478', $payload['receiver']['phone']);
        self::assertArrayNotHasKey('address', $payload['receiver'], 'paczkomat nie potrzebuje adresu odbiorcy');
        self::assertSame(['sending_method' => 'dispatch_order', 'target_point' => 'WAW23N'], $payload['custom_attributes']);
        self::assertSame([['template' => 'small']], $payload['parcels']);
        self::assertSame(['amount' => 4199.0, 'currency' => 'PLN'], $payload['insurance']);
        self::assertArrayNotHasKey('cod', $payload);
    }

    public function testCourierShipmentSplitsStreet(): void
    {
        $payload = $this->request(service: Service::COURIER_STANDARD, parcel: Parcel::dimensions(400, 300, 150, 2.5))->toPayload();

        self::assertSame([
            'street' => 'Zwoleńska',
            'building_number' => '65A',
            'city' => 'Warszawa',
            'post_code' => '04-761',
            'country_code' => 'PL',
        ], $payload['receiver']['address']);
        self::assertSame(['length' => 400, 'width' => 300, 'height' => 150, 'unit' => 'mm'], $payload['parcels'][0]['dimensions']);
        self::assertSame(['amount' => 2.5, 'unit' => 'kg'], $payload['parcels'][0]['weight']);
        self::assertArrayNotHasKey('target_point', $payload['custom_attributes']);
    }

    public function testCodRaisesInsuranceToCodAmount(): void
    {
        $payload = $this->request(service: Service::LOCKER_STANDARD, targetPoint: 'WAW23N', insuranceAmount: 10000, codAmount: 419900)->toPayload();

        self::assertSame(['amount' => 4199.0, 'currency' => 'PLN'], $payload['cod']);
        self::assertSame(['amount' => 4199.0, 'currency' => 'PLN'], $payload['insurance']);
    }

    public function testNoInsuranceWhenZero(): void
    {
        $payload = $this->request(service: Service::LOCKER_STANDARD, targetPoint: 'WAW23N', insuranceAmount: 0)->toPayload();

        self::assertArrayNotHasKey('insurance', $payload);
    }

    public function testLockerWithoutPointIsRejected(): void
    {
        $this->expectException(InvalidShipmentException::class);
        $this->expectExceptionMessage('paczkomatu');

        $this->request(service: Service::LOCKER_STANDARD)->toPayload();
    }

    public function testLandlinePhoneIsRejected(): void
    {
        $this->expectException(InvalidShipmentException::class);
        $this->expectExceptionMessage('22 123 45 67');

        $this->request(service: Service::LOCKER_STANDARD, targetPoint: 'WAW23N', phone: '22 123 45 67')->toPayload();
    }

    private function request(
        string $service,
        ?string $targetPoint = null,
        ?Parcel $parcel = null,
        ?int $insuranceAmount = null,
        ?int $codAmount = null,
        string $phone = '+48 605 203 478',
    ): ShipmentRequest {
        return new ShipmentRequest(
            service: $service,
            reference: '000000042',
            firstName: 'Jan',
            lastName: 'Kowalski',
            email: 'jan@example.com',
            phone: $phone,
            parcel: $parcel ?? Parcel::template('small'),
            targetPoint: $targetPoint,
            street: 'Zwoleńska 65A',
            city: 'Warszawa',
            postcode: '04-761',
            insuranceAmount: $insuranceAmount,
            codAmount: $codAmount,
        );
    }
}
