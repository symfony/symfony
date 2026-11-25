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
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class ParsedStylesheetTest extends TestCase
{
    private const CSS = '@property --tone{syntax:"<color>";inherits:false;initial-value:blue}@counter-style custom{system:cyclic;symbols:"X";suffix:"!"}@function --paint(){result:var(--tone)}@keyframes fade{from{opacity:0}to{opacity:1}}@position-try --above{top:0}p{color:--paint();animation:fade 1s linear both}p::before{content:counter(item,custom)}';

    public function testBundleAndDocumentParsingShareAllActualMetadataAndAnimationValues()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>'.self::CSS.'</style><p>Target</p>');
        $target = $document->querySelector('p');
        $native = new StyleResolver();
        $bundled = new StyleResolver();
        $nativeTimeline = new Timeline();
        $bundledTimeline = new Timeline();
        $bundle = $this->parse(self::CSS);
        self::assertArrayHasKey('--above', $bundle->positionTries);
        foreach ([0.0, .5, 1.0] as $time) {
            $nativeStyles = $native->resolve($document, 400, 200, [], $nativeTimeline, $time);
            $bundleStyles = $bundled->resolve($document, 400, 200, [], $bundledTimeline, $time, null, $bundle);
            self::assertSame($nativeStyles[$target], $bundleStyles[$target]);
            self::assertSame('blue', $bundleStyles[$target]['color']);
            self::assertSame($native->getPseudoStyle($target, 'before'), $bundled->getPseudoStyle($target, 'before'));
            self::assertSame('X', $bundled->getCounterStyles()->format(1, 'custom'));
        }
    }

    public function testSwitchingModesDoesNotPoisonTheSourceCacheOrRetainRemovedMetadata()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>'.self::CSS.'</style><p>Target</p>');
        $target = $document->querySelector('p');
        $resolver = new StyleResolver();
        $initial = $resolver->resolve($document, 400, 200)[$target];
        self::assertSame('blue', $initial['color']);
        $empty = $resolver->resolve($document, 400, 200, [], null, 0, null, new ParsedStylesheet())[$target];
        self::assertSame('#000000', $empty['color']);
        self::assertFalse($resolver->getCounterStyles()->has('custom'));
        $rulesWithoutDefinitions = $this->parse('p{color:--paint();animation:fade 1s linear both}');
        $timeline = new Timeline();
        self::assertSame('#000000', $resolver->resolve($document, 400, 200, [], $timeline, 0, null, $rulesWithoutDefinitions)[$target]['color']);
        self::assertFalse($timeline->hasPendingFrames(0));
        self::assertSame($initial, $resolver->resolve($document, 400, 200)[$target]);
        self::assertTrue($resolver->getCounterStyles()->has('custom'));
        $document->querySelector('style')->textContent = 'p{color:red}';
        self::assertSame('red', $resolver->resolve($document, 400, 200)[$target]['color']);
        self::assertFalse($resolver->getCounterStyles()->has('custom'));
        self::assertSame('blue', $resolver->resolve($document, 400, 200, [], null, 0, null, $this->parse(self::CSS))[$target]['color']);
        self::assertSame('red', $resolver->resolve($document, 400, 200)[$target]['color']);
    }

    private function parse(string $css): ParsedStylesheet
    {
        $parser = new CssParser();
        $rules = $parser->parse($css);

        return new ParsedStylesheet($rules, $parser->takeRegisteredProperties(), $parser->takeKeyframes(), $parser->takeCounterStyles(), $parser->takeFunctions(), $parser->takePositionTries());
    }
}
