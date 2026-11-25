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
use Symfony\Component\HtmlRenderer\Paint\Filter;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class FilterTest extends TestCase
{
    public function testCssColorFunctionsKeepOrderUnitsAndClamping()
    {
        $filter = Filter::parse('invert(100%) grayscale() brightness(50%)');
        self::assertSame([['invert', 1.0], ['grayscale', 1.0], ['brightness', 0.5]], $filter->functions);
        self::assertSame([['invert', 1.0], ['hue-rotate', 180.0]], Filter::parse('INVERT(100%) HUE-ROTATE(.5TURN)')->functions);
        self::assertSame([100, 100, 100], $filter->apply([255, 0, 0]));
        self::assertSame([0, 109, 109], Filter::parse('hue-rotate(.5turn)')->apply([255, 0, 0]));
        self::assertSame([255, 0, 0], Filter::parse('saturate(2)')->apply([255, 0, 0]));
        self::assertSame([128, 0, 128], Filter::parse('opacity(50%)')->apply([255, 0, 0], [0, 0, 255]));
    }

    public function testInvalidColorFunctionsAreRejectedAsAWhole()
    {
        foreach (['', 'none', 'invert(-1)', 'invert(1px)', 'hue-rotate(infinity)', 'invert(1) nonsense(2)', 'blur(2px)'] as $value) {
            self::assertNull(Filter::parse($value), $value);
        }
    }

    public function testSpatialFiltersAreSeparatedWithoutLosingTheirArguments()
    {
        self::assertSame(['grayscale(50%) brightness(1.2)', '1px 2px rgb(0 0 0 / .5), 0 3px red'], Filter::split('grayscale(50%) drop-shadow(1px 2px rgb(0 0 0 / .5)) blur(2px) brightness(1.2) drop-shadow(0 3px red)'));
        self::assertSame([null, ''], Filter::split('grayscale(1) invalid'));
    }

    public function testPaintingGroupsAnElementsContentWithoutInheritingTheFilter()
    {
        $renderer = new HtmlRenderer();
        $html = '<div style="filter:invert(1);background:red">A<span>B</span><i style="filter:grayscale(.5)">C</i></div><p>D</p>';
        $result = $renderer->render($html, new RenderingContext(200, 200));
        $operations = $result->getDisplayList()->getOperations();
        $filtered = array_values(array_filter($operations, static fn (array $op) => \in_array($op['type'], ['push-filter', 'pop-filter', 'text'], true)));
        self::assertSame(['text', 'push-filter', 'text', 'text', 'push-filter', 'text', 'pop-filter', 'pop-filter'], array_column($filtered, 'type'));
        self::assertSame([['invert', 1.0]], $filtered[1]['functions']);
        self::assertSame([['grayscale', 0.5]], $filtered[4]['functions']);
        self::assertSame('D', $filtered[0]['text']);
        self::assertSame($operations, $renderer->render($html, new RenderingContext(200, 200))->getDisplayList()->getOperations());
        self::assertSame($operations, json_decode(json_encode($operations, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
    }
}
