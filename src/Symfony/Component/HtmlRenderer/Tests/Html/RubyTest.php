<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class RubyTest extends TestCase
{
    public function testNativeRubyAnnotationsUseSeparateRowsAndRetainIdentity()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>a <ruby>東<rp>(</rp><rt>とう</rt><rp>)</rp></ruby> b</p>', \LIBXML_NOERROR);
        $html = $document->saveHtml();
        $result = new HtmlRenderer()->render($document, new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        $annotation = array_values(array_filter($text, static fn (array $op) => 'とう' === $op['text']))[0];
        $base = array_values(array_filter($text, static fn (array $op) => '東' === $op['text']))[0];
        self::assertLessThan($base['y'], $annotation['y']);
        self::assertSame(8.0, $annotation['font-size']);
        self::assertNotContains('(', array_column($text, 'text'));
        self::assertSame($html, $document->saveHtml());
        self::assertNotEmpty($result->getBounds($document->querySelector('rt')->firstChild));
    }

    public function testGroupingUsesExplicitBasesAndRemainingAnnotationsOnLastBase()
    {
        self::assertSame([[[[0], [1]], [2, 3, 4]]], \Symfony\Component\HtmlRenderer\Html\RubySegments::pair(['ruby-base', 'ruby-base', 'ruby-text', 'ruby-text', 'ruby-text']));
        self::assertSame([[[[0]], [2]], [[[3]], [4]]], \Symfony\Component\HtmlRenderer\Html\RubySegments::pair(['inline', 'none', 'ruby-text', 'inline', 'ruby-text']));
    }

    public function testUnderStartAndLegacyInterCharacterApproximation()
    {
        foreach (['under', 'inter-character', 'alternate'] as $position) {
            $result = new HtmlRenderer()->render('<!DOCTYPE html><style>ruby{ruby-position:'.$position.';ruby-align:start}rt{font-size:100%}</style><ruby>漢<rt>kan</rt></ruby>', new RenderingContext(400, 200));
            $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
            $base = array_values(array_filter($text, static fn (array $op) => '漢' === $op['text']))[0];
            $annotation = array_values(array_filter($text, static fn (array $op) => 'kan' === $op['text']))[0];
            self::assertSame($base['x'], $annotation['x']);
            if ('under' === $position) {
                self::assertGreaterThan($base['y'], $annotation['y']);
            } else {
                self::assertLessThan($base['y'], $annotation['y']);
            }
        }
    }

    public function testExplicitInlineAnnotationAndParenthesesRemainInline()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>rt,rp{display:inline}</style><ruby>猫<rp>(</rp><rt>ねこ</rt><rp>)</rp></ruby>', new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('猫(ねこ)', implode('', array_column($text, 'text')));
        self::assertCount(1, array_unique(array_map(static fn (array $op) => round($op['baseline'], 6), $text)));
    }

    public function testExplicitBasesPairWithAnnotationsAndRtcRemainsOneAnnotation()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><ruby><rb>今</rb><rb>日</rb><rt>こん</rt><rt>にち</rt></ruby><ruby>語<rtc>reading</rtc></ruby>', new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        $byText = array_column($text, null, 'text');
        self::assertSame($byText['今']['x'], $byText['こん']['x']);
        self::assertSame($byText['日']['x'], $byText['にち']['x']);
        self::assertLessThan($byText['語']['y'], $byText['reading']['y']);
        self::assertGreaterThan($byText['reading']['x'], $byText['語']['x']);
    }
}
