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
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class StyleResolverTest extends TestCase
{
    public function testDocumentElementSiblingFunctionsHaveNoParentElement()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html{width:calc(sibling-index() * 10px)}</style><p>Target</p>');
        self::assertNull($document->documentElement->parentElement);
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('calc(1 * 10px)', $styles[$document->documentElement]['width']);
    }

    public function testInvalidBackgroundBlendListDoesNotReplaceAValidDeclaration()
    {
        foreach (['screen, plus-lighter', 'screen,', ',screen', 'screen,,multiply'] as $invalid) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{background-blend-mode:multiply}#target{background-blend-mode:'.$invalid.'}</style><p id="target">Target</p>');
            $values = new StyleResolver()->resolve($document, 400, 200)[$document->getElementById('target')];
            self::assertSame('multiply', $values['background-blend-mode']);
        }
    }

    public function testTextDecorationShorthandCompetesWithLonghandsAndResolvesThickness()
    {
        foreach (['text-decoration:underline wavy red 10%;text-decoration-style:double' => 'double', 'text-decoration-style:double;text-decoration:underline wavy red 10%' => 'wavy'] as $declarations => $expected) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="font-size:20px;'.$declarations.'">Text</p>');
            $values = new StyleResolver()->resolve($document, 400, 200)[$document->querySelector('p')];
            self::assertSame('underline', $values['text-decoration-line']);
            self::assertSame($expected, $values['text-decoration-style']);
            self::assertSame('red', $values['text-decoration-color']);
            self::assertSame('2px', $values['text-decoration-thickness']);
        }
    }

    public function testPerNodeUserAgentDeclarationsRespectCascadeOrigins()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>*{color:blue}p::after{content:"Author"}</style><p style="padding:0">Text</p>');
        $paragraph = $document->querySelector('p');
        $source = $document->saveHtml();
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), [], 'p{width:10px;height:10px!important}');
        $styles = $resolver->resolve($document, 400, 200, [[$paragraph, ['user-agent-styles' => ['' => ['color' => 'red', 'width' => '30px', 'height' => '30px', 'padding' => '2px'], 'after' => ['content' => '"UA"', 'color' => 'green']]]]]);
        self::assertSame('blue', $styles[$paragraph]['color']);
        self::assertSame('30px', $styles[$paragraph]['width']);
        self::assertSame('10px', $styles[$paragraph]['height']);
        self::assertSame('0', $styles[$paragraph]['padding-left']);
        self::assertSame('"Author"', $resolver->getPseudoStyle($paragraph, 'after')['content']);
        self::assertSame('green', $resolver->getPseudoStyle($paragraph, 'after')['color']);
        self::assertSame($source, $document->saveHtml());
    }

    public function testScopedRulesRetainOriginalProximityPriorityWithinCascadeRank()
    {
        $cases = [
            '@scope(.card){p{color:red}}p{color:blue}' => 'red',
            'p{color:blue}@scope(.card){p{color:red}}' => 'red',
            '@scope(.card){p{color:red}}#target{color:blue}' => 'blue',
            '@scope(.card){p{color:red}}p{color:blue!important}' => 'blue',
            '@scope(.card){p{color:red!important}}p{color:blue!important}' => 'red',
            '@layer a,b;@layer a{@scope(.card){p{color:red}}}@layer b{p{color:blue}}' => 'blue',
            '@scope(.card){p{color:red}p{color:blue}}' => 'blue',
        ];
        foreach ($cases as $css => $expected) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>'.$css.'</style><div class="card"><p id="target">Text</p></div>');
            self::assertSame($expected, new StyleResolver()->resolve($document, 400, 200)[$document->getElementById('target')]['color'], $css);
        }
    }

    public function testLanguageAndDirectionSelectorsUseInheritedNativeSemantics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>span:lang(fr){color:red}span:dir(rtl){background-color:blue}</style><div lang="fr-CA" dir="rtl"><span id="match">Text</span><span id="reset" lang="" dir="ltr" data-html-renderer-lang="fr" data-html-renderer-dir="rtl">Text</span></div>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('red', $styles[$document->getElementById('match')]['color']);
        self::assertSame('blue', $styles[$document->getElementById('match')]['background-color']);
        self::assertSame('#000000', $styles[$document->getElementById('reset')]['color']);
        self::assertArrayNotHasKey('background-color', $styles[$document->getElementById('reset')]);
        self::assertFalse($document->getElementById('match')->hasAttribute('data-html-renderer-lang'));
    }

    public function testBorderImageShorthandAndSourceCompeteBeforeCascade()
    {
        foreach (['border-image:linear-gradient(red,blue) 30 / 2; border-image-source:none' => 'none', 'border-image-source:none; border-image:linear-gradient(red,blue) 30 / 2' => 'linear-gradient(red,blue)'] as $declarations => $source) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div style="'.$declarations.'">Text</div>');
            $styles = new StyleResolver()->resolve($document, 400, 200);
            self::assertSame($source, $styles[$document->querySelector('div')]['border-image-source']);
        }
        self::assertSame(['url("two.png")', 2.0], \Symfony\Component\HtmlRenderer\Css\ImageSource::imageSet('image-set("one.png" 1x, "two.png" 2x)', 1.5));
    }

    public function testPickerStylesUseDocumentPseudoWithoutChangingLegacySelector()
    {
        $selector = \Symfony\Component\HtmlRenderer\Css\Selector::parse('select::picker(select)');
        self::assertNull($selector->pseudoElement);
        self::assertSame('picker', $selector->forDocument()->pseudoElement);
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>select::picker(select){padding:3px;color:red}select:open{border-color:blue}</style><select><option>PHP</option><option>Rust</option></select>');
        $select = $document->querySelector('select');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200, [[$select, ['picker-open' => true]]]);
        self::assertSame('3px', $resolver->getPseudoStyle($select, 'picker')['padding-left']);
        self::assertSame('red', $resolver->getPseudoStyle($select, 'picker')['color']);
        self::assertSame('blue', $styles[$select]['border-left-color']);
        self::assertSame('block', $styles[$document->querySelector('option')]['display']);
    }

    public function testExplicitColorAliasesResolveBeforeValidationIncludingNestedFunctions()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{--ink:custom-ink;color:var(--ink);border:1px solid 208;background-color:color-mix(in srgb,custom-ink 50%,white)}</style><p>Text</p>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), [], '', ['custom-ink' => '#ff0000', 208 => '#00ff00', 'red' => '#0000ff']);
        $values = $resolver->resolve($document, 400, 200)[$document->querySelector('p')];
        self::assertSame('#ff0000', $values['color']);
        self::assertSame('#00ff00', $values['border-left-color']);
        self::assertSame('#ff8080', $values['background-color']);
        $document->querySelector('p')->setAttribute('style', 'color:red');
        self::assertSame('red', $resolver->resolve($document, 400, 200)[$document->querySelector('p')]['color']);
    }

    public function testSessionMutationReparsesStylesAndKeepsNativeNodeIdentity()
    {
        $session = new \Symfony\Component\HtmlRenderer\HtmlRenderer()->createSession('<!DOCTYPE html><style>p{color:red}</style><p>Text</p>', new \Symfony\Component\HtmlRenderer\RenderingContext(400, 200));
        $document = $session->getDocument();
        $paragraph = $document->querySelector('p');
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        $document->querySelector('style')->textContent = 'p{color:blue}';
        self::assertSame('blue', $session->render()->getComputedStyle($paragraph)['color']);
        $document->querySelector('style')->remove();
        self::assertSame('#000000', $session->render()->getComputedStyle($paragraph)['color']);
    }

    public function testScopedRulesMatchNativeSubtreesAndLimits()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@scope (.card) to (.limit){p{color:red}:scope{background-color:blue}}</style><div class="card"><p id="inside">A</p><div class="limit"><p id="outside">B</p></div></div>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('red', $styles[$document->getElementById('inside')]['color']);
        self::assertSame('#000000', $styles[$document->getElementById('outside')]['color']);
        self::assertSame('blue', $styles[$document->querySelector('.card')]['background-color']);
    }

    public function testImplicitScopeFollowsStyleOwnerAndInvalidatesOnMove()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><section id="one"><style>@scope{p{color:red}}</style><p id="a">A</p></section><section id="two"><p id="b">B</p></section></main>');
        $resolver = new StyleResolver();
        $first = $resolver->resolve($document, 400, 200);
        self::assertSame('red', $first[$document->getElementById('a')]['color']);
        self::assertSame('#000000', $first[$document->getElementById('b')]['color']);
        $document->getElementById('two')->appendChild($document->querySelector('style'));
        $second = $resolver->resolve($document, 400, 200);
        self::assertSame('#000000', $second[$document->getElementById('a')]['color']);
        self::assertSame('red', $second[$document->getElementById('b')]['color']);
    }

    public function testOverlayUserAgentDefaultsRespectAuthorDisplay()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><dialog>Dialog</dialog><div popover>Popup</div><div popover style="display:none">Hidden</div>');
        $resolver = new StyleResolver();
        $elements = $document->querySelectorAll('dialog, [popover]');
        $closed = $resolver->resolve($document, 400, 200);
        self::assertSame('none', $closed[$elements[0]]['display']);
        self::assertSame('none', $closed[$elements[1]]['display']);
        $opened = $resolver->resolve($document, 400, 200, [[$elements[0], ['modal']], [$elements[1], ['popover-open']], [$elements[2], ['popover-open']]]);
        self::assertSame('block', $opened[$elements[0]]['display']);
        self::assertSame('fixed', $opened[$elements[1]]['position']);
        self::assertSame('none', $opened[$elements[2]]['display']);
        self::assertSame('rgba(0, 0, 0, 0.3)', $resolver->getPseudoStyle($elements[0], 'backdrop')['background-color']);
    }

    public function testElementFunctionsResolveAfterVariablesAndBeforeShorthandExpansion()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{--edges:attr(data-gap type(<length>), 2px);padding:var(--edges);width:calc(sibling-index() * 10px);opacity:attr(data-alpha number,.5)}</style><main><p data-gap="3px" data-alpha=".7">A</p><p data-gap="bad">B</p></main>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        $paragraphs = $document->querySelectorAll('p');
        self::assertSame('3px', $styles[$paragraphs[0]]['padding-left']);
        self::assertSame('2px', $styles[$paragraphs[1]]['padding-left']);
        self::assertSame('calc(2 * 10px)', $styles[$paragraphs[1]]['width']);
        self::assertSame('.7', $styles[$paragraphs[0]]['opacity']);
        self::assertSame('.5', $styles[$paragraphs[1]]['opacity']);
    }

    public function testPresentationHintsUseSharedHtmlAlgorithmsAndAuthorOrigin()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>*{text-align:left}</style><div align="right">Text</div><table width="75%" bgcolor="#abcdef"></table><textarea cols="12"></textarea>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), [], 'table{width:10px}');
        $styles = $resolver->resolve($document, 400, 200);
        self::assertSame('left', $styles[$document->querySelector('div')]['text-align']);
        self::assertSame('75%', $styles[$document->querySelector('table')]['width']);
        self::assertSame('#abcdef', $styles[$document->querySelector('table')]['background-color']);
        self::assertSame('12ch', $styles[$document->querySelector('textarea')]['width']);
    }

    public function testLogicalEdgesCompeteWithPhysicalDeclarationsInSourceOrder()
    {
        foreach (['margin-block:1em;margin-top:0' => '0', 'margin-top:0;margin-block:1em' => '1em'] as $declarations => $top) {
            $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="'.$declarations.'">Text</p>');
            $styles = new StyleResolver()->resolve($document, 400, 200);
            self::assertSame($top, $styles[$document->querySelector('p')]['margin-top']);
            self::assertSame('1em', $styles[$document->querySelector('p')]['margin-bottom']);
        }
    }

    public function testLogicalEdgesRespectOriginDirectionAndWritingMode()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{margin-top:0;direction:rtl;margin-inline-start:3px;writing-mode:vertical-lr;inline-size:30px;block-size:40px}</style><p>Text</p>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), [], 'p{margin-block:1em}');
        $values = $resolver->resolve($document, 400, 200)[$document->querySelector('p')];
        self::assertSame('0', $values['margin-top']);
        self::assertSame('3px', $values['margin-bottom']);
        self::assertSame('1em', $values['margin-left']);
        self::assertSame('30px', $values['height']);
        self::assertSame('40px', $values['width']);
    }

    public function testHtmlDirectionHintInheritsAndAuthorStyleOverrides()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div dir="rtl"><span id="inherited">Text</span><span id="override" dir="rtl" style="direction:ltr">Text</span></div>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('rtl', $styles[$document->getElementById('inherited')]['direction']);
        self::assertSame('ltr', $styles[$document->getElementById('override')]['direction']);
    }

    public function testAutomaticHtmlDirectionUsesFirstStrongCharacter()
    {
        if (!class_exists(\IntlChar::class)) {
            self::markTestSkipped('The optional intl extension is required for automatic direction.');
        }
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p dir="auto">123 שלום</p>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('rtl', $styles[$document->querySelector('p')]['direction']);
    }

    public function testUserAgentOriginPrecedesAuthorAndReversesForImportant()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p {color:blue; width:20px!important}</style><p style="height:30px!important">Text</p>');
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), [], 'p { color:red; width:10px!important; height:10px!important; padding:1px }');
        $values = $resolver->resolve($document, 400, 200)[$document->querySelector('p')];
        self::assertSame('blue', $values['color']);
        self::assertSame('10px', $values['width']);
        self::assertSame('10px', $values['height']);
        self::assertSame('1px', $values['padding']);
    }

    public function testStylesheetRulesAreReusedUntilSourceChanges()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { color: red }</style><p>Text</p>');
        $resolver = new StyleResolver();
        $property = new \ReflectionProperty(StyleResolver::class, 'rules');
        $resolver->resolve($document, 400, 200);
        $first = $property->getValue($resolver);
        $resolver->resolve($document, 800, 200);
        self::assertSame($first[0], $property->getValue($resolver)[0]);
        $document->querySelector('style')->textContent = 'p { color: blue }';
        $styles = $resolver->resolve($document, 800, 200);
        self::assertNotSame($first[0], $property->getValue($resolver)[0]);
        self::assertSame('blue', $styles[$document->querySelector('p')]['color']);
    }

    public function testCascadeInheritanceCustomPropertiesAndState()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>body { color: red; --space: 2.5px } #item { color: blue } .item { color: green !important; padding: var(--space) } button:hover { width: 12.5px }</style><body><button id="item" class="item" style="color: purple">Hello</button></body>');
        $element = $document->getElementById('item');
        $styles = new StyleResolver()->resolve($document, 800.5, 600, [[$element, ['hover']]]);
        self::assertSame('green', $styles[$element]['color']);
        self::assertSame('2.5px', $styles[$element]['padding']);
        self::assertSame('12.5px', $styles[$element]['width']);
        self::assertFalse($element->hasAttribute('data-html-renderer-state'));
    }

    public function testMutationsAndViewportAreResolvedAgain()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { width: 1.5px } @media (min-width: 500px) { p { width: 2.5px } }</style><p id="item">Hello</p>');
        $element = $document->getElementById('item');
        $resolver = new StyleResolver();
        self::assertSame('1.5px', $resolver->resolve($document, 400, 300)[$element]['width']);
        self::assertSame('2.5px', $resolver->resolve($document, 600, 300)[$element]['width']);
        $element->setAttribute('style', 'width: 3.75px');
        self::assertSame('3.75px', $resolver->resolve($document, 600, 300)[$element]['width']);
    }

    public function testUserAgentDisplayAndHiddenElements()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { color: red }</style><p><span id="inline">Text</span></p><div hidden id="hidden">Hidden</div>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('none', $styles[$document->querySelector('head')]['display']);
        self::assertSame('none', $styles[$document->querySelector('style')]['display']);
        self::assertSame('inline', $styles[$document->getElementById('inline')]['display']);
        self::assertSame('none', $styles[$document->getElementById('hidden')]['display']);
    }

    public function testVariableExpansionIsBounded()
    {
        $css = '--v0: x;';
        for ($i = 1; $i < 24; ++$i) {
            $css .= '--v'.$i.': var(--v'.($i - 1).')var(--v'.($i - 1).');';
        }
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="'.$css.'; width: var(--v23)">Text</p>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertArrayNotHasKey('width', $styles[$document->querySelector('p')]);
    }

    public function testComputedTypographyDoesNotCompoundInheritedLengths()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div style="font-size: 2em; line-height: 1.5"><section><p id="inherited">A</p><p id="smaller" style="font-size: 50%">B</p></section></div>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('32px', $styles[$document->getElementById('inherited')]['font-size']);
        self::assertSame('48px', $styles[$document->getElementById('inherited')]['line-height']);
        self::assertSame('16px', $styles[$document->getElementById('smaller')]['font-size']);
        self::assertSame('24px', $styles[$document->getElementById('smaller')]['line-height']);
    }

    public function testAbsoluteLineHeightIsInheritedWithoutScaling()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div style="font-size: 2em; line-height: 150%"><p style="font-size: 50%">A</p></div>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('16px', $styles[$document->querySelector('p')]['font-size']);
        self::assertSame('48px', $styles[$document->querySelector('p')]['line-height']);
    }

    public function testComputedStylesRetainNativeWrappersAfterResolverIsReleased()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="color: red">A</p>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        gc_collect_cycles();
        self::assertSame('red', $styles[$document->querySelector('p')]['color']);
    }

    public function testLayersImportantReversalSupportsAndNestingUseSharedParser()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@layer first, second; @layer first { p { color: red !important; width: 1px } } @layer second { p { color: blue !important; width: 2px } } p { color: green !important; width: 3px; & > span { height: 4.5px } } @supports (display: flex) { p { display: flex } } @supports (unknown: value) { p { width: 99px } }</style><p><span>Text</span></p>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('red', $styles[$document->querySelector('p')]['color']);
        self::assertSame('3px', $styles[$document->querySelector('p')]['width']);
        self::assertSame('flex', $styles[$document->querySelector('p')]['display']);
        self::assertSame('4.5px', $styles[$document->querySelector('span')]['height']);
    }

    public function testRegisteredCustomPropertiesApplyInitialValuesAndInheritance()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --space { syntax: "<length>"; inherits: false; initial-value: 2.5px } div { --space: 5px } p { padding: var(--space) }</style><div><p>Text</p></div>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('2.5px', $styles[$document->querySelector('p')]['padding']);
    }

    public function testSupportsRejectsUnknownValuesOfKnownProperties()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { width: 1px } @supports (display: imaginary) { p { width: 2px } } @supports (width: imaginary) { p { width: 3px } }</style><p>Text</p>');
        $styles = new StyleResolver()->resolve($document, 800, 600);
        self::assertSame('1px', $styles[$document->querySelector('p')]['width']);
    }

    public function testCyclicCustomPropertiesUseConsumerFallbacks()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="--a: var(--a,red); color: var(--a,blue)">A</p><p style="--a: var(--b); --b: var(--a,red); color: var(--a,green)">B</p>');
        $styles = new StyleResolver()->resolve($document, 100, 100);
        $elements = iterator_to_array($document->querySelectorAll('p'));
        self::assertSame('blue', $styles[$elements[0]]['color']);
        self::assertSame('green', $styles[$elements[1]]['color']);
    }

    public function testVariableLikeTextInStringsRemainsLiteral()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before { content: "var(--missing)" }</style><p>A</p>');
        $resolver = new StyleResolver();
        $resolver->resolve($document, 100, 100);
        self::assertSame('"var(--missing)"', $resolver->getPseudoStyle($document->querySelector('p'), 'before')['content']);
    }

    public function testShorthandsParticipateInCascadeBeforeValueResolution()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { margin: 1px 2px !important; margin-left: 99px; border: 2px solid red; border-left-color: blue; --padding: 3px 4px; padding: var(--padding) }</style><p>Text</p>');
        $styles = new StyleResolver()->resolve($document, 100, 100);
        $style = $styles[$document->querySelector('p')];
        self::assertSame('2px', $style['margin-left']);
        self::assertSame('3px', $style['padding-top']);
        self::assertSame('4px', $style['padding-left']);
        self::assertSame('2px', $style['border-right-width']);
        self::assertSame('blue', $style['border-left-color']);
        self::assertSame('red', $style['border-right-color']);
    }

    public function testInvalidLengthsDoNotOverrideEarlierDeclarations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="width: 12.5px; width: banana; padding: 3px; padding: -2px">Text</p>');
        $style = new StyleResolver()->resolve($document, 100, 100)[$document->querySelector('p')];
        self::assertSame('12.5px', $style['width']);
        self::assertSame('3px', $style['padding-left']);
    }

    public function testFontShorthandAndDeviceDefaultMetricsStayIndependent()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div id="default">Default</div><p style="font: italic bold 12px/1.5 monospace"><span style="font-weight: lighter">Text</span></p>');
        $styles = new StyleResolver(new ResourceLoader(), new FontStyle('test-family', 10, 10, 500))->resolve($document, 100, 100);
        $default = $styles[$document->getElementById('default')];
        self::assertSame('test-family', $default['font-family']);
        self::assertSame('10px', $default['font-size']);
        self::assertSame('10px', $default['line-height']);
        self::assertSame('500', $default['font-weight']);
        $font = $styles[$document->querySelector('p')];
        self::assertSame('12px', $font['font-size']);
        self::assertSame('18px', $font['line-height']);
        self::assertSame('italic', $font['font-style']);
        self::assertSame('700', $font['font-weight']);
        self::assertSame('400', $styles[$document->querySelector('span')]['font-weight']);
    }

    public function testAuthorCustomPropertyCannotCollideWithTypographyState()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>body { --html-line-height-multiplier: red; font-size: 10px; line-height: 2 } p { font-size: 20px; color: var(--html-line-height-multiplier) }</style><p>Text</p>');
        $styles = new StyleResolver()->resolve($document, 100, 100);
        self::assertSame('red', $styles[$document->querySelector('body')]['--html-line-height-multiplier']);
        self::assertSame('red', $styles[$document->querySelector('p')]['color']);
        self::assertSame('40px', $styles[$document->querySelector('p')]['line-height']);
    }

    public function testExplicitAndPseudoInheritancePreserveUnitlessLineHeight()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>body { font-size: 10px; line-height: 2 } p { font-size: 20px; line-height: inherit } p::before { content: "A"; font-size: 30px }</style><p>Text</p>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 100, 100);
        self::assertSame('40px', $styles[$document->querySelector('p')]['line-height']);
        self::assertSame('60px', $resolver->getPseudoStyle($document->querySelector('p'), 'before')['line-height']);
    }
}
