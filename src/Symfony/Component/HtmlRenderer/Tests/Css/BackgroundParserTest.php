<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\BackgroundParser;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class BackgroundParserTest extends TestCase
{
    public function testShorthandRetainsLayerIdentityAndSlashInsideQuotedUrls()
    {
        $parts = BackgroundParser::components('url("assets/a/b.png") right 2px bottom 3px / 12px auto no-repeat content-box padding-box, linear-gradient(red, blue) center/cover round border-box');
        self::assertSame('url("assets/a/b.png"), linear-gradient(red, blue)', $parts['background-image']);
        self::assertSame('right 2px bottom 3px, center', $parts['background-position']);
        self::assertSame('12px auto, cover', $parts['background-size']);
        self::assertSame('no-repeat, round', $parts['background-repeat']);
        self::assertSame('content-box, border-box', $parts['background-origin']);
        self::assertSame('padding-box, border-box', $parts['background-clip']);
    }

    public function testLaterBackgroundShorthandResetsEarlierImageAndSize()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div id="a" style="background-image:url(a.png);background-size:12px;background:red"></div>');
        $result = new HtmlRenderer()->render($document, new RenderingContext(100, 100));
        $style = $result->getComputedStyle($document->getElementById('a'));
        self::assertSame('none', $style['background-image']);
        self::assertSame('auto', $style['background-size']);
        self::assertSame('repeat', $style['background-repeat']);
    }

    public function testLayoutPartUsesExistingPercentageAndPositionParsing()
    {
        $length = static fn (string $value, bool $vertical): ?float => preg_match('/^(-?\d+(?:\.\d+)?)px$/', $value, $match) ? (float) $match[1] : null;
        $position = BackgroundParser::part('background-position', 'left top, right 2.5px bottom 3.25px', $length, 1);
        self::assertSame([['percent' => 0.0, 'offset' => 2.5, 'fromEnd' => true], ['percent' => 0.0, 'offset' => 3.25, 'fromEnd' => true]], $position);
        self::assertSame([['percent' => 25.0, 'offset' => 0.0, 'fromEnd' => false], null], BackgroundParser::part('background-size', '25% auto', $length));
        self::assertSame(['repeat', 'no-repeat'], BackgroundParser::part('background-repeat', 'repeat-x', $length));
    }
}
