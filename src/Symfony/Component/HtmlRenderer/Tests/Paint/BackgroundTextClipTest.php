<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class BackgroundTextClipTest extends TestCase
{
    public function testTextMasksUseActualFragmentedGlyphGeometryAndRemainPlainData()
    {
        $html = '<!doctype html><div style="width:40px;background:linear-gradient(red,blue);background-clip:text;-webkit-text-fill-color:transparent">AB <b style="font-weight:700">CD EF</b></div>';
        $list = new HtmlRenderer()->render($html, new RenderingContext(100, 100))->getDisplayList()->getOperations();
        $gradient = array_values(array_filter($list, static fn (array $op): bool => 'gradient' === $op['type']))[0];
        self::assertSame(['AB ', 'CD', 'EF'], array_column($gradient['text-mask'], 'text'));
        self::assertSame([0.0, 0.0, 19.2], array_column($gradient['text-mask'], 'y'));
        self::assertSame(700, $gradient['text-mask'][1]['font-weight']);
        self::assertSame($list, json_decode(json_encode($list, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testSolidTextClipRetainsDescendantTransformsAndOverflowClips()
    {
        $html = '<!doctype html><div style="background:red;background-clip:text;transform:translateX(10px)"><span style="display:inline-block;width:8px;overflow:hidden;transform:translateX(3px)">AB</span></div>';
        $list = new HtmlRenderer()->render($html, new RenderingContext(100, 100))->getDisplayList()->getOperations();
        $rectangle = array_values(array_filter($list, static fn (array $op): bool => 'rectangle' === $op['type']))[0];
        self::assertSame(3.0, $rectangle['text-mask'][0]['matrix'][4]);
        self::assertNotEmpty($rectangle['text-mask'][0]['clips']);
        self::assertSame(8.0, $rectangle['text-mask'][0]['clips'][0]['width']);
    }
}
