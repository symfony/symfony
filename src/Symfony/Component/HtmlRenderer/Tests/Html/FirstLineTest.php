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

class FirstLineTest extends TestCase
{
    public function testFirstLinePreservesInlineOverridesAndWrapping()
    {
        $source = '<!DOCTYPE html><style>p{width:160px;color:gray}p::first-line{color:green;text-decoration:underline}b{color:blue}</style><p>First <b>BLUE</b> more words to wrap onto following gray lines</p>';
        $document = \Dom\HTMLDocument::createFromString($source);
        $html = $document->saveHtml();
        $result = new HtmlRenderer()->render($document, new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('green', $text[0]['color']);
        self::assertSame('blue', $text[1]['color']);
        self::assertSame('underline', $text[0]['text-decoration-line']);
        self::assertSame('underline', $text[1]['text-decoration-line']);
        self::assertSame('gray', $text[4]['color']);
        self::assertSame('none', $text[4]['text-decoration-line']);
        self::assertSame($html, $document->saveHtml());
        foreach ($text as $operation) {
            self::assertSame([], array_filter(array_keys($operation), static fn (string $key) => str_starts_with($key, '-renderer-')));
        }
    }

    public function testExplicitSameColorAndFirstLetterOverrideFirstLineButInheritanceDoesNot()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p{color:gray}p::first-line{color:green}p::first-letter{color:red}.same{color:gray}.inherit{color:inherit}</style><p>A<span class="same">B</span><span class="inherit">C</span>D</p>', new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame(['red', 'gray', 'green', 'green'], array_column($text, 'color'));
    }

    public function testMetricDeclarationsDoNotChangeLayoutForSupportedPaintOnlySubset()
    {
        $renderer = new HtmlRenderer();
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $width = 0.0;
                foreach (str_split($text) as $character) {
                    $width += ('l' === $character ? 2.25 : 7.5) * $font->size / 16;
                }

                return new TextMetrics($width, $font->size * .8, $font->size * .2);
            }
        };
        $context = new RenderingContext(400, 200, $measurer);
        $html = '<p style="width:80px">Alpha beta gamma delta</p>';
        $plain = $renderer->render('<!DOCTYPE html>'.$html, $context);
        $styled = $renderer->render('<!DOCTYPE html><style>p::first-line{font-size:100px;font-weight:bold;color:green}</style>'.$html, $context);
        $geometry = static fn ($result) => array_map(static fn (array $op) => [$op['text'], $op['x'], $op['y'], $op['font-size'], $op['font-weight']], array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type'])));
        self::assertSame($geometry($plain), $geometry($styled));
        foreach ($styled->getDocument()->querySelectorAll('*') as $element) {
            self::assertSame([], array_filter(array_keys($styled->getComputedStyle($element)), static fn (string $key) => str_starts_with($key, '-renderer-')));
        }
    }

    public function testGeneratedBeforeParticipatesWithoutLosingItsExplicitColor()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p{width:80px;color:gray}p::before{content:"Prefix ";color:purple}p::first-line{color:green;text-decoration:underline}</style><p>word second third</p>', new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame('Prefix', $text[0]['text']);
        self::assertSame(48.0, $text[0]['width']);
        self::assertStringContainsString('content:"Prefix ";', $result->getDocument()->querySelector('style')->textContent);
        self::assertSame('purple', $text[0]['color']);
        self::assertSame('underline', $text[0]['text-decoration-line']);
        self::assertSame('gray', $text[1]['color']);
        self::assertSame('none', $text[1]['text-decoration-line']);
    }
}
