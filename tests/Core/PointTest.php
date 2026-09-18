<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\Point;
use PHPUnit\Framework\TestCase;

final class PointTest extends TestCase
{
    public function testFromApi(): void
    {
        $point = Point::fromApi([
            'name' => 'WAW23N',
            'status' => 'Operating',
            'location_description' => 'Przy sklepie Netto',
            'distance' => 48,
            'opening_hours' => '24/7',
            'address' => ['line1' => 'Zwoleńska 59', 'line2' => '04-761 Warszawa'],
        ]);

        self::assertNotNull($point);
        self::assertSame('WAW23N — Zwoleńska 59, 04-761 Warszawa', $point->label());
        self::assertTrue($point->operating);
        self::assertSame(48, $point->jsonSerialize()['distance']);
    }

    public function testDisabledPointIsFlagged(): void
    {
        $point = Point::fromApi(['name' => 'WAW01M', 'status' => 'Disabled']);

        self::assertNotNull($point);
        self::assertFalse($point->operating);
    }

    public function testItemWithoutNameIsIgnored(): void
    {
        self::assertNull(Point::fromApi(['status' => 'Operating']));
    }
}
