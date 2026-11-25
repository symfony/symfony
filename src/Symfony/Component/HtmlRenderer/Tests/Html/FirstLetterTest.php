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

class FirstLetterTest extends TestCase
{
    public function testFirstLetterFragmentDoesNotIntroduceAWordBreak()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p{width:32px}p::first-letter{color:red}</style><p>Alpha</p>', new RenderingContext(400, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(['A', 'lpha'], array_column($text, 'text'));
        self::assertSame($text[0]['y'], $text[1]['y']);
    }

    public function testPunctuationAndCombiningGraphemeUseFirstLetterStyleWithoutDomMutation()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::first-letter{color:red;font-weight:bold;font-size:2em}</style><p>“Álpha</p>');
        $source = $document->saveHtml();
        $result = new HtmlRenderer()->render($document, new RenderingContext(800, 300));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(['“Á', 'lpha'], array_column($text, 'text'));
        self::assertSame('red', $text[0]['color']);
        self::assertSame(700, $text[0]['font-weight']);
        self::assertSame(32.0, $text[0]['font-size']);
        self::assertSame($source, $document->saveHtml());
        $nodes = [];
        $visit = static function ($fragment) use (&$visit, &$nodes) {
            if (null !== $fragment->text) {
                $nodes[] = $fragment->node;
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($result->getLayout());
        self::assertSame([$document->querySelector('p')->firstChild, $document->querySelector('p')->firstChild], $nodes);
    }

    public function testNestedInlineStylesArePreservedUnlessExplicitlyOverridden()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p::first-letter{font-weight:bold}.red::first-letter{color:red}</style><p><span style="color:blue">Alpha</span></p><p class="red"><span style="color:blue">Beta</span></p>', new RenderingContext(800, 300));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(['A', 'lpha', 'B', 'eta'], array_column($text, 'text'));
        self::assertSame('blue', $text[0]['color']);
        self::assertSame('red', $text[2]['color']);
    }

    public function testGeneratedBeforeContentParticipatesInFirstLetterSelection()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p::before{content:"Before "}p::first-letter{color:red}</style><p>After</p>', new RenderingContext(800, 300));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']));
        self::assertSame(['B', 'efore ', 'After'], array_column($text, 'text'));
        self::assertSame('red', $text[0]['color']);
        self::assertSame('#000000',$text[2]['color']);
    }
}
