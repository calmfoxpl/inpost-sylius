<?php

/**
 * Własny autoloader PSR-4 idzie pierwszy, żeby testy zawsze sprawdzały TEN katalog, a nie kopię
 * paczki zainstalowaną w vendor/ sklepu. Autoloader Composera (paczki albo sklepu, w którym
 * paczka leży jako repozytorium path) dokładamy, jeśli jest — potrzebują go tylko testy API.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $roots = [
        'Calmfox\\InPostBundle\\Tests\\' => __DIR__.'/',
        'Calmfox\\InPostBundle\\' => __DIR__.'/../src/',
    ];

    foreach ($roots as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $path = $dir.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';
        if (is_file($path)) {
            require_once $path;
        }

        return;
    }
}, true, true);

foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        break;
    }
}
