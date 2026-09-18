<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Repository;

use Calmfox\InPostBundle\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Settings> */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function findSettings(): ?Settings
    {
        return $this->find(Settings::SINGLETON_ID);
    }

    public function saveSandbox(bool $sandbox): Settings
    {
        $settings = $this->getOrCreate($sandbox);
        $settings->setSandbox($sandbox);
        $this->getEntityManager()->flush();

        return $settings;
    }

    /** @param bool $defaultSandbox tryb dla wiersza, którego jeszcze nie ma */
    public function getOrCreate(bool $defaultSandbox): Settings
    {
        $settings = $this->findSettings();
        if (null === $settings) {
            $settings = new Settings($defaultSandbox);
            $this->getEntityManager()->persist($settings);
        }

        return $settings;
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
