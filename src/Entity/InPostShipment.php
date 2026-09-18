<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Entity;

use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ShipmentInterface;

/**
 * Wszystko, co InPost dokłada do przesyłki Syliusa: wybrany punkt, identyfikator w ShipX,
 * status i numer nadania. Osobna tabela zamiast pól w zamówieniu — sklep instaluje paczkę
 * bez ruszania własnych encji, a usunięcie przesyłki z koszyka sprząta wiersz kaskadą.
 *
 * Relacja celuje w interfejs; Sylius podmienia go na klasę sklepu przez resolve_target_entities.
 */
#[ORM\Entity(repositoryClass: InPostShipmentRepository::class)]
#[ORM\Table(name: 'calmfox_inpost_shipment')]
#[ORM\Index(name: 'calmfox_inpost_shipment_status_idx', columns: ['status'])]
class InPostShipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: ShipmentInterface::class)]
    #[ORM\JoinColumn(name: 'shipment_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ShipmentInterface $shipment;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $service;

    #[ORM\Column(name: 'target_point', type: Types::STRING, length: 32, nullable: true)]
    private ?string $targetPoint = null;

    #[ORM\Column(name: 'target_point_address', type: Types::STRING, length: 255, nullable: true)]
    private ?string $targetPointAddress = null;

    #[ORM\Column(name: 'shipx_id', type: Types::STRING, length: 32, nullable: true)]
    private ?string $shipxId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'tracking_number', type: Types::STRING, length: 64, nullable: true)]
    private ?string $trackingNumber = null;

    /** Tryb, w którym przesyłka powstała w ShipX; sandboxowa istnieje tylko w sandboxie. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $sandbox = false;

    #[ORM\Column(name: 'last_error', type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'dispatched_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;

    public function __construct(ShipmentInterface $shipment, string $service)
    {
        $this->shipment = $shipment;
        $this->service = $service;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): ShipmentInterface
    {
        return $this->shipment;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function setService(string $service): void
    {
        $this->service = $service;
    }

    public function getTargetPoint(): ?string
    {
        return $this->targetPoint;
    }

    public function getTargetPointAddress(): ?string
    {
        return $this->targetPointAddress;
    }

    public function setTargetPoint(?string $name, ?string $address = null): void
    {
        $this->targetPoint = $name;
        $this->targetPointAddress = null === $name ? null : $address;
    }

    public function getShipxId(): ?string
    {
        return $this->shipxId;
    }

    public function isDispatched(): bool
    {
        return null !== $this->shipxId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function markDispatched(string $shipxId, string $status, bool $sandbox = false): void
    {
        $this->shipxId = $shipxId;
        $this->sandbox = $sandbox;
        $this->status = $status;
        $this->lastError = null;
        $this->dispatchedAt = new \DateTimeImmutable();
    }

    public function updateFromShipX(string $status, ?string $trackingNumber): void
    {
        $this->status = $status;
        if (null !== $trackingNumber && '' !== $trackingNumber) {
            $this->trackingNumber = $trackingNumber;
        }
    }

    public function markFailed(string $error): void
    {
        $this->lastError = $error;
    }
}
