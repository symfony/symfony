<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

final class StartingStyleTest extends TestCase
{
    public function testFirstShownTransitionUsesResolvedStartingValuesAndRestartsAfterDisplayNone()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{--from:0;opacity:1;transition:opacity 1s linear}@starting-style{p{opacity:var(--from)}}</style><p>text</p>');
        $element = $document->querySelector('p');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        self::assertSame('0', $resolver->resolve($document, 640, 480, [], $timeline, 0)[$element]['opacity']);
        self::assertTrue($timeline->hasPendingFrames(0));
        self::assertSame('0.5', $resolver->resolve($document, 640, 480, [], $timeline, 0.5)[$element]['opacity']);
        self::assertSame('1', $resolver->resolve($document, 640, 480, [], $timeline, 1)[$element]['opacity']);
        $element->setAttribute('style', 'display:none');
        self::assertSame('none', $resolver->resolve($document, 640, 480, [], $timeline, 2)[$element]['display']);
        self::assertFalse($timeline->hasPendingFrames(2));
        $element->removeAttribute('style');
        self::assertSame('0', $resolver->resolve($document, 640, 480, [], $timeline, 3)[$element]['opacity']);
        self::assertSame('0.5', $resolver->resolve($document, 640, 480, [], $timeline, 3.5)[$element]['opacity']);
    }

    public function testStartingBaselineSharesCascadeInlineImportanceAndValueResolution()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>section{font-size:20px}p{--size:150%;opacity:1}@starting-style{#first{opacity:.25;font-size:var(--size);padding:1px 2px}#second{opacity:.25!important}}#first{opacity:.75}</style><section><p id="first">first</p><p id="second" style="opacity:.9!important">second</p></section>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $first = $document->getElementById('first');
        $second = $document->getElementById('second');
        $baseline = $resolver->getCascadeRecord($first)['starting-style'];
        self::assertSame('.75', $baseline['computed']['opacity']);
        self::assertSame('30px', $baseline['computed']['font-size']);
        self::assertSame('2px', $baseline['computed']['padding-left']);
        self::assertSame('.9', $resolver->getCascadeRecord($second)['starting-style']['computed']['opacity']);
        self::assertSame('.75', $styles[$first]['opacity']);
        self::assertSame('20px', $styles[$first]['font-size']);
        self::assertArrayNotHasKey('padding-left', $styles[$first]);
    }

    public function testEquivalentStartingValuesDoNotCreateTransitionsThroughComputedUnitNormalization()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{font-size:1em;opacity:1;transition:all 1s linear}@starting-style{p{opacity:1}}</style><p>text</p>');
        $timeline = new Timeline();
        $styles = new StyleResolver()->resolve($document, 640, 480, [], $timeline, 0);
        self::assertSame('16px', $styles[$document->querySelector('p')]['font-size']);
        self::assertFalse($timeline->hasPendingFrames(0));
    }
}
