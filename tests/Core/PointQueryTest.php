<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\PointQuery;
use PHPUnit\Framework\TestCase;

final class PointQueryTest extends TestCase
{
    public function testPostcodeInAnyNotation(): void
    {
        foreach (['04-761', '04761', '04 761', ' 04-761 '] as $input) {
            $query = PointQuery::parse($input);
            self::assertNotNull($query, $input);
            self::assertSame(['relative_post_code' => '04-761'], $query->toApiParameters(), $input);
        }
    }

    public function testPointCodeIsUppercased(): void
    {
        $query = PointQuery::parse('waw23n');

        self::assertNotNull($query);
        self::assertSame(PointQuery::KIND_POINT, $query->kind);
        self::assertSame(['name' => 'WAW23N'], $query->toApiParameters());
    }

    public function testCity(): void
    {
        $query = PointQuery::parse('Nowy Dwór Mazowiecki');

        self::assertNotNull($query);
        self::assertSame(['city' => 'Nowy Dwór Mazowiecki'], $query->toApiParameters());
    }

    public function testGarbageIsRejected(): void
    {
        foreach (['', 'ab', '12', '<script>', 'Warszawa; DROP'] as $input) {
            self::assertNull(PointQuery::parse($input), $input);
        }
    }
}
