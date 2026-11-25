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
use Symfony\Component\HtmlRenderer\Css\ColorValue;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class ColorValueTest extends TestCase
{
    public function testStandardNamesAndTransparencyDoNotUseTerminalPalettes()
    {
        self::assertSame([[1.0, 0.0, 0.0], 1.0], ColorValue::parse('red'));
        self::assertSame([[0.0, 128 / 255, 0.0], 1.0], ColorValue::parse('green'));
        self::assertSame([[1.0, 0.0, 0.0], 0.5], ColorValue::parse('rgb(255 0 0 / 50%)'));
        self::assertEqualsWithDelta(128 / 255, ColorValue::parse('#ff000080')[1], 0.000001);
    }

    public function testRelativeAndMixedColorsUseSharedColorSpaceAlgorithms()
    {
        $relative = ColorValue::parse('rgb(from red calc(r / 2) g b / 0.25)');
        self::assertEqualsWithDelta(0.5, $relative[0][0], 0.00001);
        self::assertSame(0.25, $relative[1]);
        self::assertSame([[0.5, 0.0, 0.5], 1.0], ColorValue::parse('color-mix(in srgb, red, blue)'));
        self::assertSame([[1.0, 1.0, 1.0], 1.0], ColorValue::parse('contrast-color(black)'));
        self::assertSame([[1.0, 1.0, 1.0], 1.0], ColorValue::parse('light-dark(black, white)', 'dark'));
    }

    public function testInitialCanvasTextFollowsExplicitColorScheme()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new MonospaceTextMeasurer(), ['prefers-color-scheme' => 'dark']);
        self::assertSame('#ffffff', $resolver->resolve($document, 100, 100)[$document->querySelector('p')]['color']);
    }
}
