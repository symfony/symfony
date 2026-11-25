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
use Symfony\Component\HtmlRenderer\Css\CssRule;
use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Css\DeclarationExpander;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

class CascadeRecordTest extends TestCase
{
    public function testRecordDistinguishesExpansionExplicitInitialAndInheritedDefaults()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>div{color:red;margin:10px;margin-left:20px;flex-grow:initial}p{color:inherit}</style><div><p>text</p></div>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $div = $document->querySelector('div');
        $record = $resolver->getCascadeRecord($div);
        self::assertSame($styles[$div], $record['computed']);
        self::assertSame('margin-left', $record['declarations']['margin-left']['source']);
        self::assertSame('margin', $record['declarations']['margin-right']['source']);
        self::assertSame('10px', $record['declarations']['margin-right']['source-value']);
        self::assertGreaterThan($record['declarations']['margin-right']['order'], $record['declarations']['margin-left']['order']);
        self::assertTrue($record['declarations']['flex-grow']['initial']);
        self::assertSame('initial', $record['declarations']['flex-grow']['value']);
        self::assertArrayNotHasKey('font-family', $record['declarations']);
        $child = $resolver->getCascadeRecord($document->querySelector('p'));
        self::assertSame('red', $child['computed']['color']);
        self::assertSame('inherit', $child['declarations']['color']['value']);
        self::assertFalse($child['declarations']['color']['initial']);
    }

    public function testInheritedPropertiesAndCompoundBorderColorsStaySemantic()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>div{empty-cells:hide;image-rendering:pixelated;text-align:start;direction:rtl}p{border-color:red green blue yellow;text-align:match-parent}</style><div><p>text</p></div>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $child = $document->querySelector('p');
        self::assertSame('hide', $styles[$child]['empty-cells']);
        self::assertSame('pixelated', $styles[$child]['image-rendering']);
        self::assertSame('red green blue yellow', $styles[$child]['border-color']);
        self::assertSame('right', $styles[$child]['text-align']);
        self::assertArrayNotHasKey('empty-cells', $resolver->getCascadeRecord($child)['declarations']);
    }

    public function testBorderZeroIsAWidthEvenWhenADeviceDefinesNumericColorAliases()
    {
        $colors = static fn (string $value): bool => \in_array($value, ['0', '208'], true);
        $zero = array_column(DeclarationExpander::expand(new Declaration('border', '0'), $colors), 'value', 'property');
        self::assertSame('0', $zero['border-top-width']);
        self::assertSame('currentcolor', $zero['border-top-color']);
        $palette = array_column(DeclarationExpander::expand(new Declaration('border', '1px solid 208'), $colors), 'value', 'property');
        self::assertSame('1px', $palette['border-top-width']);
        self::assertSame('208', $palette['border-top-color']);
    }

    public function testCompiledNativeSelectorsRetainTheirActualTreeSubject()
    {
        foreach (['details::details-content', 'select::picker(select)', 'input[list]:open'] as $source) {
            $selector = Selector::parse($source);
            $compiled = $selector->forNativeTree();
            self::assertSame($selector->forHtml(), $compiled->forDocument()->forHtml());
            self::assertSame($selector->specificity, $compiled->specificity);
            self::assertSame($selector->pseudoElement, $compiled->pseudoElement);
        }
    }

    public function testOverscrollAxesParticipateInTheNormalCascade()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{overscroll-behavior:AUTO CONTAIN;overscroll-behavior-x:var(--mode);--mode:NONE;overscroll-behavior-y:invalid}div{overscroll-behavior-x:auto!important;overscroll-behavior:contain none}</style><p>x</p><div>x</div>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $paragraph = $styles[$document->querySelector('p')];
        self::assertSame('none', $paragraph['overscroll-behavior-x']);
        self::assertSame('contain', $paragraph['overscroll-behavior-y']);
        $div = $styles[$document->querySelector('div')];
        self::assertSame('auto', $div['overscroll-behavior-x']);
        self::assertSame('none', $div['overscroll-behavior-y']);
    }

    public function testPseudoShorthandsResolveTheirOwnCustomPropertySource()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{--pad:1px 2px}p::before{content:"x";padding:var(--pad)}</style><p>x</p>');
        $resolver = new StyleResolver();
        $resolver->resolve($document, 640, 480);
        $pseudo = $resolver->getPseudoStyle($document->querySelector('p'), 'before');
        self::assertSame('1px', $pseudo['padding-top']);
        self::assertSame('2px', $pseudo['padding-left']);
        $record = $resolver->getCascadeRecord($document->querySelector('p'), 'before');
        self::assertSame($pseudo, $record['computed']);
        self::assertSame('padding', $record['declarations']['padding-left']['source']);
        self::assertSame('var(--pad)', $record['declarations']['padding-left']['source-value']);
        self::assertSame('2px', $record['resolved']['padding-left']);
    }

    public function testReleasingComputedStylesPreservesResultsAndRecomputesMutations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{color:var(--ink, red)}p::before{content:"before";color:inherit}</style><p>text</p>');
        $element = $document->querySelector('p');
        $resolver = new StyleResolver();
        $original = $resolver->resolve($document, 640, 480);
        $before = $resolver->getCascadeRecord($element, 'before');
        $resolver->releaseComputedStyles();
        $element->setAttribute('style', '--ink:blue');
        $updated = $resolver->resolve($document, 640, 480);
        self::assertSame('red', $original[$element]['color']);
        self::assertSame('red', $before['computed']['color']);
        self::assertSame('blue', $updated[$element]['color']);
        self::assertSame('blue', $resolver->getPseudoStyle($element, 'before')['color']);
        self::assertSame('--ink', $resolver->getCascadeRecord($element)['declarations']['--ink']['source']);
    }

    public function testMatchedRuleKeysPreserveInputIdentityForOrdinaryAndPseudoRules()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p class="found">text</p><div>other</div>');
        $rules = [
            17 => new CssRule(Selector::parseList('p')[0], [new Declaration('color', 'red')], [], 0),
            29 => new CssRule(Selector::parseList('p::before')[0], [new Declaration('content', '"before"')], [], 1),
            41 => new CssRule(Selector::parseList('.missing')[0], [new Declaration('color', 'blue')], [], 2),
        ];
        $resolver = new StyleResolver();
        $resolver->resolve($document, 640, 480, [], null, 0, null, new ParsedStylesheet($rules));
        self::assertSame([17, 29], $resolver->getCascadeRecord($document->querySelector('p'))['matched-rules']);
        self::assertSame([], $resolver->getCascadeRecord($document->querySelector('div'))['matched-rules']);
    }

    public function testCaretPropertiesInheritIntoFields()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>section{caret-shape:underscore;caret-color:#123456;caret-animation:manual}</style><section><input></section>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $field = $document->querySelector('input');
        self::assertSame('underscore', $styles[$field]['caret-shape']);
        self::assertSame('#123456', $styles[$field]['caret-color']);
        self::assertSame('manual', $styles[$field]['caret-animation']);
    }

    public function testDetachedStyleTargetsUseTheirVisualParentAndOwnInlineDeclarations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>@property --shade{syntax:"<color>";inherits:false;initial-value:blue}section{font-size:20px;color:red;direction:rtl;--shade:green}.detached{color:yellow}section:first-child{font-weight:700}</style><section>text</section>');
        $source = $document->saveHtml();
        $parent = $document->querySelector('section');
        $detached = $document->createElement('span');
        $detached->setAttribute('class', 'detached');
        $detached->setAttribute('style', 'display:flex;font-size:150%;padding:1.25px;color:currentColor');
        $child = $document->createElement('span');
        $child->setAttribute('style', 'color:var(--shade);font-size:2em');
        $parents = new \SplObjectStorage();
        $parents[$detached] = $parent;
        $parents[$child] = $detached;
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480, [], null, 0, null, null, null, [], $parents);
        self::assertSame('flex', $styles[$detached]['display']);
        self::assertSame('30px', $styles[$detached]['font-size']);
        self::assertSame('red', $styles[$detached]['color']);
        self::assertSame('rtl', $styles[$detached]['direction']);
        self::assertSame('1.25px', $styles[$detached]['padding-left']);
        self::assertSame('blue', $styles[$child]['color']);
        self::assertSame('60px', $styles[$child]['font-size']);
        self::assertSame([], $resolver->getCascadeRecord($detached)['matched-rules']);
        self::assertSame($source, $document->saveHtml());
        self::assertFalse($detached->isConnected);
    }

    public function testDetachedStyleTargetCannotUseAnotherDocumentsElement()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p>text</p>');
        $foreign = \Dom\HTMLDocument::createEmpty()->createElement('span');
        $parents = new \SplObjectStorage();
        $parents[$foreign] = $document->documentElement;
        $this->expectException(InvalidArgumentException::class);
        new StyleResolver()->resolve($document, 640, 480, [], null, 0, null, null, null, [], $parents);
    }

    public function testDetachedStyleTargetCycleIsRejectedWithoutWalkingIt()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><p>text</p>');
        $first = $document->createElement('span');
        $second = $document->createElement('span');
        $parents = new \SplObjectStorage();
        $parents[$first] = $second;
        $parents[$second] = $first;
        $this->expectException(InvalidArgumentException::class);
        new StyleResolver()->resolve($document, 640, 480, [], null, 0, null, null, null, [], $parents);
    }
}
