<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Wszystko, czego ShipX potrzebuje do utworzenia przesyłki, w jednostkach sklepu:
 * kwoty w groszach (tak liczy Sylius), telefon i adres tak, jak wpisał je klient.
 * Przeliczenia na zapis ShipX robi {@see toPayload()} — w jednym miejscu, pod testem.
 */
final class ShipmentRequest
{
    public function __construct(
        public readonly string $service,
        public readonly string $reference,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone,
        public readonly Parcel $parcel,
        public readonly ?string $targetPoint = null,
        public readonly ?string $company = null,
        public readonly ?string $street = null,
        public readonly ?string $city = null,
        public readonly ?string $postcode = null,
        public readonly string $countryCode = 'PL',
        public readonly ?int $insuranceAmount = null,
        public readonly ?int $codAmount = null,
        public readonly string $currency = 'PLN',
        public readonly string $sendingMethod = 'dispatch_order',
        public readonly ?string $comments = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidShipmentException gdy danych nie da się nadać — z komunikatem dla operatora
     */
    public function toPayload(): array
    {
        $phone = PhoneNumber::normalize($this->phone);
        if (null === $phone) {
            throw new InvalidShipmentException(sprintf('Telefon odbiorcy „%s" nie jest polskim numerem komórkowym — InPost wysyła na niego kod odbioru.', $this->phone));
        }

        $receiver = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $phone,
        ];
        if (null !== $this->company && '' !== trim($this->company)) {
            $receiver['company_name'] = $this->company;
        }

        $attributes = ['sending_method' => $this->sendingMethod];

        if (Service::requiresPoint($this->service)) {
            if (null === $this->targetPoint || '' === $this->targetPoint) {
                throw new InvalidShipmentException('Zamówienie nie ma wybranego paczkomatu.');
            }
            $attributes['target_point'] = $this->targetPoint;
        } else {
            if (null === $this->street || null === $this->city || null === $this->postcode) {
                throw new InvalidShipmentException('Przesyłka kurierska wymaga pełnego adresu odbiorcy.');
            }
            $address = StreetAddress::fromLine($this->street);
            $receiver['address'] = [
                'street' => $address->street,
                'building_number' => $address->buildingNumber,
                'city' => $this->city,
                'post_code' => $this->postcode,
                'country_code' => $this->countryCode,
            ];
        }

        $payload = [
            'service' => $this->service,
            'reference' => $this->reference,
            'receiver' => $receiver,
            'parcels' => [$this->parcel->toPayload()],
            'custom_attributes' => $attributes,
        ];

        // Pobranie bez ubezpieczenia ShipX odrzuca, a ubezpieczenie niższe niż pobranie
        // zostawia sklep z niepokrytą różnicą — podnosimy je więc co najmniej do kwoty pobrania.
        $insurance = $this->insuranceAmount;
        if (null !== $this->codAmount && $this->codAmount > 0) {
            $payload['cod'] = ['amount' => self::money($this->codAmount), 'currency' => $this->currency];
            $insurance = max($insurance ?? 0, $this->codAmount);
        }
        if (null !== $insurance && $insurance > 0) {
            $payload['insurance'] = ['amount' => self::money($insurance), 'currency' => $this->currency];
        }
        if (null !== $this->comments && '' !== trim($this->comments)) {
            $payload['comments'] = mb_substr(trim($this->comments), 0, 100);
        }

        return $payload;
    }

    private static function money(int $minorUnits): float
    {
        return round($minorUnits / 100, 2);
    }
}
