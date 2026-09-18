<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle;

use Calmfox\InPostBundle\DependencyInjection\CalmfoxInPostExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CalmfoxInPostBundle extends Bundle
{
    /** Jedyne źródło prawdy o wersji; w composer.json pola „version" nie ma — Composer liczy je z tagu. */
    public const VERSION = '0.2.0';

    /** Klucz konfiguracji to `calmfox_inpost` (InPost to jedno słowo), nie wyliczone z nazwy klasy `calmfox_in_post`. */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new CalmfoxInPostExtension();
        }

        return $this->extension;
    }

    /** Układ nowych bundli Symfony: config/, templates/, translations/ i public/ leżą w korzeniu paczki. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
