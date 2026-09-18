<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/** Pozycja „InPost" w sekcji Konfiguracja menu panelu — obok metod dostawy, do których należy. */
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');
        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild('calmfox_inpost', ['route' => 'calmfox_inpost_admin_settings'])
            ->setLabel('calmfox_inpost.menu')
            ->setLabelAttribute('icon', 'tabler:package');
    }
}
