<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\InvalidShipmentException;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\ShipmentRequest;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/** Zamówienie Syliusa → żądanie nadania. Jedyne miejsce, które zna oba modele naraz. */
final class ShipmentRequestFactory
{
    /** @param list<string> $codPaymentMethods kody metod płatności, które oznaczają pobranie */
    public function __construct(
        private readonly InsurancePolicy $insurancePolicy,
        private readonly array $codPaymentMethods,
        private readonly string $sendingMethod,
    ) {
    }

    /** @param int|null $insuranceAmount w groszach; null = według polityki, 0 = bez ubezpieczenia */
    public function create(InPostShipment $inPostShipment, Parcel $parcel, ?int $insuranceAmount = null): ShipmentRequest
    {
        $order = $inPostShipment->getShipment()->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new InvalidShipmentException('Przesyłka nie jest przypięta do zamówienia.');
        }

        $address = $order->getShippingAddress();
        if (null === $address) {
            throw new InvalidShipmentException('Zamówienie nie ma adresu dostawy.');
        }

        $email = $order->getCustomer()?->getEmail();
        if (null === $email || '' === $email) {
            throw new InvalidShipmentException('Zamówienie nie ma adresu e-mail klienta — InPost wysyła na niego powiadomienia.');
        }

        return new ShipmentRequest(
            service: $inPostShipment->getService(),
            reference: (string) $order->getNumber(),
            firstName: (string) $address->getFirstName(),
            lastName: (string) $address->getLastName(),
            email: $email,
            phone: (string) $address->getPhoneNumber(),
            parcel: $parcel,
            targetPoint: $inPostShipment->getTargetPoint(),
            company: $address->getCompany(),
            street: $address->getStreet(),
            city: $address->getCity(),
            postcode: $address->getPostcode(),
            countryCode: $address->getCountryCode() ?? 'PL',
            insuranceAmount: $insuranceAmount ?? $this->insurancePolicy->amountFor($order->getTotal()),
            codAmount: $this->codAmount($order),
            currency: $order->getCurrencyCode() ?? 'PLN',
            sendingMethod: $this->sendingMethod,
        );
    }

    /** Kwota pobrania w groszach albo null, gdy zamówienie jest opłacane inaczej. */
    public function codAmount(OrderInterface $order): ?int
    {
        $payment = $order->getLastPayment();
        if (!$payment instanceof PaymentInterface) {
            return null;
        }

        $isCod = \in_array($payment->getMethod()?->getCode(), $this->codPaymentMethods, true);
        $isPaid = PaymentInterface::STATE_COMPLETED === $payment->getState();

        return $isCod && !$isPaid ? $order->getTotal() : null;
    }
}
