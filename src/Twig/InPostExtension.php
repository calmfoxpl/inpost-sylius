<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Twig;

use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Controller\AdminShipmentController;
use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\Links;
use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Core\ShipmentStatus;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\Environment;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Calmfox\InPostBundle\Shipping\ShipmentRequestFactory;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Dane dla dwóch szablonów paczki; szablony same nie sięgają do usług. */
final class InPostExtension extends AbstractExtension
{
    /** @param array{length: int, width: int, height: int, weight: float} $courierParcel wymiary w mm, waga w kg */
    public function __construct(
        private readonly MethodMap $methodMap,
        private readonly InPostShipmentRepository $repository,
        private readonly ShipmentRequestFactory $requestFactory,
        private readonly InsurancePolicy $insurancePolicy,
        private readonly ShipXClients $clients,
        private readonly Environment $environment,
        private readonly string $lockerTemplate,
        private readonly array $courierParcel,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calmfox_inpost_point_methods', $this->methodMap->pointMethodCodes(...)),
            new TwigFunction('calmfox_inpost_for_shipment', $this->forShipment(...)),
            new TwigFunction('calmfox_inpost_admin_rows', $this->adminRows(...)),
        ];
    }

    public function forShipment(?ShipmentInterface $shipment): ?InPostShipment
    {
        return null === $shipment ? null : $this->repository->findOneByShipment($shipment);
    }

    /** @return list<array<string, mixed>> */
    public function adminRows(OrderInterface $order): array
    {
        $rows = [];
        foreach ($this->repository->findByOrder($order) as $shipment) {
            $cod = $this->requestFactory->codAmount($order);
            $status = (string) $shipment->getStatus();
            // Nadana przesyłka żyje w trybie, w którym powstała; nienadana pójdzie w bieżącym.
            $sandbox = $shipment->isDispatched() ? $shipment->isSandbox() : $this->environment->isSandbox();

            $rows[] = [
                'shipment' => $shipment,
                'requiresPoint' => Service::requiresPoint($shipment->getService()),
                'csrfTokenId' => AdminShipmentController::csrfTokenId($shipment),
                'statusKey' => '' === $status ? null : ShipmentStatus::translationKey($status),
                'hasLabel' => ShipmentStatus::hasLabel($status),
                'isFinal' => ShipmentStatus::isFinal($status),
                'codAmount' => $cod,
                'insuranceAmount' => $this->insurancePolicy->amountFor($order->getTotal()),
                'insuranceExceeded' => $this->insurancePolicy->exceedsLimit($order->getTotal()),
                'configured' => $this->clients->get($sandbox)->isConfigured(),
                'sandbox' => $sandbox,
                'trackingUrl' => null !== $shipment->getTrackingNumber() && !$sandbox ? Links::tracking($shipment->getTrackingNumber()) : null,
                'managerUrl' => Links::manager($sandbox),
                'lockerTemplate' => $this->lockerTemplate,
                'courierParcel' => [
                    'length' => $this->courierParcel['length'] / 10,
                    'width' => $this->courierParcel['width'] / 10,
                    'height' => $this->courierParcel['height'] / 10,
                    'weight' => $this->courierParcel['weight'],
                ],
            ];
        }

        return $rows;
    }
}
