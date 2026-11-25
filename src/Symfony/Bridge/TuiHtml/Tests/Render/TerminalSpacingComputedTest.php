<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\Length;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class TerminalSpacingComputedTest extends TestCase
{
    public function testComputedSpacingConvertsDeviceFontAdvancesExactlyOnce()
    {
        foreach ([16, 32] as $height) {
            foreach (['1ch', '1em', '8px', '3'] as $value) {
                $terminal = new TerminalTextMeasurer(8, $height);
                $provider = new class($terminal) implements TextMeasurerInterface {
                    public ?FontStyle $used = null;

                    public function __construct(private TerminalTextMeasurer $terminal)
                    {
                    }

                    public function measure(string $text, FontStyle $font): TextMetrics
                    {
                        if ('a b' === $text) {
                            $this->used = $font;
                        }

                        return $this->terminal->measure($text, $font);
                    }
                };
                $context = new RenderingContext(400, 200, $provider, null, new TerminalLayoutMetrics($terminal), new FontStyle('monospace', 16, 16));
                $result = new HtmlRenderer()->render('<!doctype html><p style="word-spacing:'.$value.'">a b</p>', $context);
                $expected = Length::toWholeCells($value, new MediaContext()->withCellSize(new CellSize(8, $height)), false) * 8;
                self::assertNotNull($provider->used);
                self::assertSame((float) $expected, $provider->used->wordSpacing, $value.' at cell height '.$height);
                if (32 === $height && '1ch' === $value) {
                    self::assertSame('4px', $result->getComputedStyle($result->getDocument()->querySelector('p'))['word-spacing']);
                    $inherited = new HtmlRenderer()->render('<!doctype html><p style="font-size:20px;word-spacing:1ch"><span style="font-size:10px">a b</span></p>', $context);
                    self::assertSame('4px', $inherited->getComputedStyle($inherited->getDocument()->querySelector('span'))['word-spacing']);
                    self::assertSame(8.0, $provider->used->wordSpacing);
                }
            }
        }
    }
}
