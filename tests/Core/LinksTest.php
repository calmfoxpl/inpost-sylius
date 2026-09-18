<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\Links;
use PHPUnit\Framework\TestCase;

final class LinksTest extends TestCase
{
    public function testEveryOperatorLinkIsHttpsAndHasATranslationInBothLanguages(): void
    {
        $root = \dirname(__DIR__, 2).'/translations/messages.';
        $pl = (string) file_get_contents($root.'pl.yaml');
        $en = (string) file_get_contents($root.'en.yaml');

        foreach (Links::forOperator() as $link) {
            self::assertStringStartsWith('https://', $link['url']);
            self::assertStringContainsString("        {$link['key']}: ", $pl, $link['key']);
            self::assertStringContainsString("        {$link['key']}: ", $en, $link['key']);
        }
    }

    public function testTrackingLinkCarriesTheNumber(): void
    {
        self::assertSame('https://inpost.pl/sledzenie-przesylek?number=620999000000000000000001', Links::tracking('620999000000000000000001'));
    }

    public function testManagerFollowsTheMode(): void
    {
        self::assertSame(Links::MANAGER_SANDBOX, Links::manager(true));
        self::assertSame(Links::MANAGER, Links::manager(false));
    }
}
