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
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class HighlightTest extends TestCase
{
    public function testAcrossInlineNodesLaterRegistrationAndRemoval()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>::highlight(found){color:red;background-color:yellow}::highlight(current){color:blue;font-weight:bold}</style><p>a <em>big</em>   cat, BIG cat</p>', new RenderingContext(400, 200));
        $source = $session->getDocument()->saveHtml();
        $session->highlight('found', 'big cat');
        $session->highlight('current', ['cat']);
        $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertContains('red', array_column($text, 'color'));
        self::assertContains('blue', array_column($text, 'color'));
        self::assertContains(700, array_column($text, 'font-weight'));
        self::assertSame($source, $session->getDocument()->saveHtml());
        $session->removeHighlight('current');
        $session->highlight('found', 'big cat', true);
        $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertNotContains('blue', array_column($text, 'color'));
        self::assertContains('red', array_column($text, 'color'));
    }

    public function testTransformsPreserveSourceMatchingAndWholeLeafCapitalization()
    {
        foreach ([['uppercase', 'straße', 'ß', ['STRA', 'SS', 'E']], ['capitalize', 'needle', 'ed', ['Ne', 'ed', 'le']]] as [$transform, $source, $needle, $expected]) {
            $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>::highlight(h){color:red}p{text-transform:'.$transform.'}</style><p>'.$source.'</p>', new RenderingContext(400, 200));
            $session->highlight('h', $needle, true);
            $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
            self::assertSame($expected, array_column($text, 'text'));
            self::assertSame('red', $text[1]['color']);
        }
    }

    public function testFirstLetterOverHighlightOverFirstLineAndControlValuesExcluded()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>p::first-letter{color:red}p::first-line{color:green}::highlight(h){color:blue}</style><p>Alpha beta</p><input value="Alpha">', new RenderingContext(400, 200));
        $session->highlight('h', 'Alpha');
        $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('red', $text[0]['color']);
        self::assertSame('blue', $text[1]['color']);
        self::assertSame('green', $text[2]['color']);
        self::assertNotSame('blue', $text[array_key_last($text)]['color']);
    }

    public function testUnstyledAndInvalidNames()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><p>abc</p>', new RenderingContext(400, 200));
        $session->highlight('none', 'b');
        $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('abc', $text[0]['text']);
        $this->expectException(\Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException::class);
        $session->highlight('not a name', 'b');
    }

    public function testHighlightFontWeightIsMeasuredBeforeWrappingAndPreservesNativeIdentity()
    {
        $measure = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(mb_strlen($text) * ($font->weight >= 700 ? 8.0 : 4.0), 8, 2);
            }
        };
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>p{width:32px}::highlight(h){font-weight:bold;background:yellow}</style><p>one two</p>', new RenderingContext(400, 200, $measure));
        $native = $session->getDocument()->querySelector('p')->firstChild;
        $session->highlight('h', 'one');
        $result = $session->render();
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame(24.0, $text[0]['width']);
        self::assertGreaterThan($text[0]['y'], $text[array_key_last($text)]['y']);
        $visit = static function ($fragment) use (&$visit, $native): void {
            if (null !== $fragment->text) {
                self::assertSame($native, $fragment->node);
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($result->getLayout());
    }

    public function testReregisteringMovesHighlightAboveOtherRegistrations()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>::highlight(a){color:red}::highlight(b){color:blue}</style><p>word</p>', new RenderingContext(400, 200));
        $session->highlight('a', 'word');
        $session->highlight('b', 'word');
        $session->highlight('a', 'word');
        $text = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('red', $text[0]['color']);
    }
}
