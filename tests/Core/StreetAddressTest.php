<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\StreetAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StreetAddressTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function lines(): iterable
    {
        yield 'zwykły adres' => ['Zwoleńska 65A', 'Zwoleńska', '65A'];
        yield 'z lokalem' => ['Marszałkowska 10/12', 'Marszałkowska', '10/12'];
        yield 'lokal słownie' => ['al. Jana Pawła II 12 m. 3', 'al. Jana Pawła II', '12 m. 3'];
        yield 'ulica z liczbą w nazwie' => ['3 Maja 5', '3 Maja', '5'];
        yield 'przecinek przed numerem' => ['Długa, 7', 'Długa', '7'];
        yield 'wieś bez ulic' => ['Kowalewo', '', 'Kowalewo'];
        yield 'nadmiarowe spacje' => ['  Krucza   16  ', 'Krucza', '16'];
    }

    #[DataProvider('lines')]
    public function testSplit(string $line, string $street, string $number): void
    {
        $address = StreetAddress::fromLine($line);

        self::assertSame($street, $address->street);
        self::assertSame($number, $address->buildingNumber);
    }
}
