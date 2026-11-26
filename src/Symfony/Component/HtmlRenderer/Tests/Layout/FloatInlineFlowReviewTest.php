<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class FloatInlineFlowReviewTest extends TestCase
{
    public function testNormalWrappingUsesTheWiderIntervalWhenTheLeftFloatEnds()
    {
        $html = '<style>body,p{margin:0}body{font-size:10px;line-height:10px}.left{float:left;width:20px;height:10px}.right{float:right;width:30px;height:40px}</style><div class="right"></div><div class="left"></div><p>Ada Lovelace wrote</p>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(120, 100));
        $lastWord = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type'] && 'wrote' === $operation['text']));

        self::assertCount(1, $lastWord);
        self::assertSame(10.0, $lastWord[0]['y']);
        self::assertSame(0.0, $lastWord[0]['x']);
        self::assertSame(25.0, $lastWord[0]['width']);
    }

    public function testTallAtomicInlineAvoidsFloatsIntersectingItsWholeHeight()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>body{margin:0;font-size:10px;line-height:10px}.first{float:right;width:80px;height:10px}.second{float:right;width:110px;height:40px}span{display:inline-block;width:30px;height:25px}</style><div class="first"></div><div class="second"></div><span id="atomic">A</span>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(120, 100));
        $second = $result->getBounds($document->querySelector('.second'))[0];
        self::assertSame([10.0, 10.0, 110.0, 40.0], [$second->x, $second->y, $second->width, $second->height]);
        $atomic = $result->getBounds($document->getElementById('atomic'))[0];
        // An independent Chrome geometry probe gives the same y=50, below both right floats.
        self::assertSame([0.0, 50.0, 30.0, 25.0], [$atomic->x, $atomic->y, $atomic->width, $atomic->height]);
    }

    public function testUnbreakableWordMovesBelowTheFloatWhenItFitsTheFullLine()
    {
        $result = (new HtmlRenderer())->render('<style>body,p{margin:0}body{font-size:10px;line-height:10px}.left{float:left;width:80px;height:20px}</style><div class="left"></div><p>Unbreakable</p>', new RenderingContext(120, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertCount(1, $text);
        self::assertSame('Unbreakable', $text[0]['text']);
        self::assertSame([0.0, 20.0, 55.0], [$text[0]['x'], $text[0]['y'], $text[0]['width']]);
    }

    public function testRelocatingTallAtomicContentFinishesThePrecedingTextLine()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>body,p{margin:0}body{font-size:10px;line-height:10px}.first{float:right;width:80px;height:10px}.second{float:right;width:110px;height:40px}.atomic{display:inline-block;width:30px;height:25px}</style><div class="first"></div><div class="second"></div><p>A <span id="atomic" class="atomic">B</span></p>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(120, 100));
        $before = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type'] && 'A' === trim($operation['text'])));
        self::assertCount(1, $before);
        self::assertSame([0.0, 0.0], [$before[0]['x'], $before[0]['y']]);
        $atomic = $result->getBounds($document->getElementById('atomic'))[0];
        self::assertSame([0.0, 50.0, 30.0, 25.0], [$atomic->x, $atomic->y, $atomic->width, $atomic->height]);
    }

    public function testClearingForcedBreakAdvancesBelowBothFloats()
    {
        $result = (new HtmlRenderer())->render('<style>body,p{margin:0}body{font-size:10px;line-height:10px}.left{float:left;width:20px;height:10px}.right{float:right;width:30px;height:40px}</style><div class="right"></div><div class="left"></div><p>A<br style="clear:both">B</p>', new RenderingContext(120, 100));
        $after = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type'] && 'B' === $operation['text']));
        self::assertCount(1, $after);
        self::assertSame([0.0, 40.0], [$after[0]['x'], $after[0]['y']]);
    }

    public function testBaselineExpansionUsesTheWholeProspectiveLineBoxForFloatExclusion()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>body{margin:0;font-size:10px;line-height:10px}.first{float:right;width:80px;height:10px}.second{float:right;width:110px;height:40px}span{font-size:20px}</style><div class="first"></div><div class="second"></div><span id="text">AA</span>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(120, 100));
        $text = $result->getBounds($document->getElementById('text'))[0];
        // The 20px font has an 11px baseline in a 10px line; the parent's descent makes a 13px line box.
        self::assertSame([0.0, 50.0, 20.0, 10.0], [$text->x, $text->y, $text->width, $text->height]);
    }

    public function testProportionalRunsKeepTheirLineWhenItsOriginMovesMoreThanOnce()
    {
        $measurer = new class implements \Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface {
            public function measure(string $text, \Symfony\Component\HtmlRenderer\Font\FontStyle $font): \Symfony\Component\HtmlRenderer\Font\TextMetrics
            {
                $width = 0.0;
                preg_match_all('/\\X/u', $text, $characters);
                foreach ($characters[0] as $character) {
                    $width += $font->size * match ($character) {
                        '.' => .2, 'i' => .1, default => .5,
                    };
                }

                return new \Symfony\Component\HtmlRenderer\Font\TextMetrics($width, $font->size * .8, $font->size * .2);
            }
        };
        $html = '<style>body,p{margin:0}body{font-size:10px;line-height:10px}.first{float:left;width:80px;height:10px}.second{float:left;width:100px;height:10px}.third{float:left;width:110px;height:40px}</style><div class="first"></div><div class="second"></div><div class="third"></div><p>.<span style="font-size:20px">i</span><span style="font-size:50px">i</span></p>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(120, 100, $measurer));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['.', 'i', 'i'], array_column($text, 'text'));
        self::assertSame([110.0, 112.0, 114.0], array_column($text, 'x'));
        self::assertSame([12.0, 9.0, 0.0], array_column($text, 'y'));
        self::assertSame([2.0, 2.0, 5.0], array_column($text, 'width'));
    }
}
