<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

use Calmfox\InPostBundle\Api\ShipXClient;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\Core\InvalidShipmentException;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\ShipmentStatus;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Nadanie i odświeżanie przesyłki. Działa synchronicznie, w żądaniu operatora: POST do ShipX
 * trwa poniżej sekundy, a operator chce od razu widzieć, czy się udało i dlaczego nie.
 * Kolejka niczego by tu nie uprościła — dołożyłaby tylko stan „czeka na workera".
 */
final class Dispatcher
{
    /** @var \Closure(int): void */
    private readonly \Closure $sleep;

    /** @param (\Closure(int): void)|null $sleep podmieniane w testach, żeby nie czekać naprawdę */
    public function __construct(
        private readonly ShipXClient $client,
        private readonly ShipmentRequestFactory $requestFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $confirmationAttempts = 4,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $seconds): void { sleep($seconds); };
    }

    /**
     * @param int|null $insuranceAmount w groszach; null = według polityki, 0 = bez ubezpieczenia
     *
     * @throws InvalidShipmentException|ShipXException z komunikatem dla operatora
     */
    public function dispatch(InPostShipment $shipment, Parcel $parcel, ?int $insuranceAmount = null): void
    {
        if ($shipment->isDispatched()) {
            throw new InvalidShipmentException(sprintf('Przesyłka jest już nadana w InPost (%s).', $shipment->getShipxId()));
        }

        try {
            $payload = $this->requestFactory->create($shipment, $parcel, $insuranceAmount)->toPayload();
            $created = $this->client->createShipment($payload);
        } catch (InvalidShipmentException|ShipXException $e) {
            $shipment->markFailed($e instanceof ShipXException ? $e->getOperatorMessage() : $e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }

        $id = $created['id'] ?? null;
        if (!\is_scalar($id) || '' === (string) $id) {
            throw new ShipXException('InPost przyjął żądanie, ale nie zwrócił identyfikatora przesyłki.');
        }

        $shipment->markDispatched((string) $id, \is_string($created['status'] ?? null) ? $created['status'] : 'created');
        $this->apply($shipment, $created);
        $this->entityManager->flush();

        // Numer nadania pojawia się, gdy ShipX kupi ofertę — zwykle po 1–3 s. Czekamy chwilę,
        // żeby operator dostał go od razu; jeśli nie zdąży, zostaje przycisk „Odśwież" i polecenie sync.
        for ($attempt = 0; $attempt < $this->confirmationAttempts && null === $shipment->getTrackingNumber(); ++$attempt) {
            ($this->sleep)(1);

            try {
                $this->refresh($shipment);
            } catch (ShipXException) {
                break;
            }
        }
    }

    /** @throws ShipXException */
    public function refresh(InPostShipment $shipment): void
    {
        $shipxId = $shipment->getShipxId();
        if (null === $shipxId) {
            return;
        }

        $this->apply($shipment, $this->client->getShipment($shipxId));
        $this->entityManager->flush();
    }

    /** @throws ShipXException|InvalidShipmentException */
    public function label(InPostShipment $shipment): string
    {
        $shipxId = $shipment->getShipxId();
        if (null === $shipxId) {
            throw new InvalidShipmentException('Przesyłka nie została jeszcze nadana.');
        }

        if (!ShipmentStatus::hasLabel((string) $shipment->getStatus())) {
            $this->refresh($shipment);
        }
        if (!ShipmentStatus::hasLabel((string) $shipment->getStatus())) {
            throw new InvalidShipmentException('InPost jeszcze nie potwierdził przesyłki — etykieta będzie dostępna za chwilę.');
        }

        return $this->client->getLabel($shipxId);
    }

    /** @param array<string, mixed> $data */
    private function apply(InPostShipment $shipment, array $data): void
    {
        $status = \is_string($data['status'] ?? null) ? $data['status'] : (string) $shipment->getStatus();
        $tracking = \is_string($data['tracking_number'] ?? null) ? $data['tracking_number'] : null;

        $shipment->updateFromShipX($status, $tracking);

        // Numer trafia też do przesyłki Syliusa: stamtąd biorą go e-mail „wysłano" i konto klienta.
        if (null !== $tracking && '' !== $tracking && null === $shipment->getShipment()->getTracking()) {
            $shipment->getShipment()->setTracking($tracking);
        }
    }
}
