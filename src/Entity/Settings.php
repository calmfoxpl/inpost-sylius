<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Entity;

use Calmfox\InPostBundle\Repository\SettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ustawienia, które zmienia operator w panelu — dziś jedno: tryb sandbox. Zawsze jeden wiersz.
 *
 * Dane konta (tokeny) celowo tu NIE mieszkają: zostają w konfiguracji/środowisku, osobno dla
 * produkcji i sandboxa. Panel przełącza, którego kompletu używamy; nie przechowuje sekretów
 * w bazie i nie pokazuje ich w przeglądarce.
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
