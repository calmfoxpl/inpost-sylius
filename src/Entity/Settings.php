<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Entity;

use Calmfox\InPostBundle\Repository\SettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ustawienia, które zmienia operator w panelu: tryb sandbox i dane obu kont ShipX. Zawsze jeden wiersz.
 *
 * Tokeny leżą tu wyłącznie zaszyfrowane ({@see \Calmfox\InPostBundle\Core\SecretBox}) i nigdy nie
 * wracają do przeglądarki: panel umie je zapisać, podmienić i usunąć, ale nie pokazać.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'calmfox_inpost_settings')]
class Settings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $sandbox;

    #[ORM\Column(name: 'api_token', type: Types::TEXT, nullable: true)]
    private ?string $apiToken = null;

    #[ORM\Column(name: 'organization_id', type: Types::STRING, length: 32, nullable: true)]
    private ?string $organizationId = null;

    #[ORM\Column(name: 'sandbox_api_token', type: Types::TEXT, nullable: true)]
    private ?string $sandboxApiToken = null;

    #[ORM\Column(name: 'sandbox_organization_id', type: Types::STRING, length: 32, nullable: true)]
    private ?string $sandboxOrganizationId = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(bool $sandbox)
    {
        $this->sandbox = $sandbox;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function setSandbox(bool $sandbox): void
    {
        $this->sandbox = $sandbox;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getEncryptedToken(bool $sandbox): ?string
    {
        return $sandbox ? $this->sandboxApiToken : $this->apiToken;
    }

    public function setEncryptedToken(bool $sandbox, ?string $encrypted): void
    {
        if ($sandbox) {
            $this->sandboxApiToken = $encrypted;
        } else {
            $this->apiToken = $encrypted;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getOrganizationId(bool $sandbox): ?string
    {
        return $sandbox ? $this->sandboxOrganizationId : $this->organizationId;
    }

    public function setOrganizationId(bool $sandbox, ?string $organizationId): void
    {
        $organizationId = null === $organizationId || '' === trim($organizationId) ? null : trim($organizationId);
        if ($sandbox) {
            $this->sandboxOrganizationId = $organizationId;
        } else {
            $this->organizationId = $organizationId;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
