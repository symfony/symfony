<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Css\Selector;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;

final class SelectorMatchingTest extends TestCase
{
    // Each element is deeper than the parent the selectors name: the native engine only tested the nearest ancestor
    private const HTML = '<!doctype html><div class="a"><section class="b"><p id="x"></p></section></div><div class="c"><section class="b"><span><p id="y"></p></span></section></div><nav><a><span><p id="z"></p></span></a></nav><ul><li><ol><li id="w"></li></ol></li></ul>';

    #[DataProvider('provideSelectors')]
    public function testLogicalPseudoClassesMatchBeforeACombinator(string $selector, array $ids)
    {
        $document = \Dom\HTMLDocument::createFromString(self::HTML, \LIBXML_NOERROR);
        $matched = array_map(static fn (\Dom\Element $element) => $element->id, iterator_to_array($document->querySelectorAll(Selector::parse($selector)->forHtml())));
        $this->assertSame($ids, $matched);
    }

    public static function provideSelectors(): iterable
    {
        yield 'is' => [':is(.a) p', ['x']];
        yield 'is list' => [':is(.a, .c) p', ['x', 'y']];
        yield 'is with complex arguments' => [':is(nav a, footer) p', ['z']];
        yield 'is with a child combinator inside' => [':is(nav > a) p', ['z']];
        yield 'is twice' => [':is(ul, ol) :is(ol) > li', ['w']];
        yield 'where' => [':where(.a) p', ['x']];
        yield 'not' => [':not(.b) p', ['x', 'y', 'z']];
        yield 'not of a complex selector' => ['p:not(:is(div section p))', ['z']];
        yield 'has' => [':has(> section) p', ['x', 'y']];
        yield 'nth-child of a selector' => [':nth-last-child(1 of .c) p', ['y']];
        yield 'nested' => [':is(div :is(section)) p', ['x', 'y']];
        yield 'in a compound' => ['div:is(.a, .c) p', ['x', 'y']];
        yield 'lang, rewritten to :is()' => [':lang(fr) p', []];
    }

    public function testAnImplicitUniversalSelectorMatchesDeeperAncestors()
    {
        $document = \Dom\HTMLDocument::createFromString(self::HTML, \LIBXML_NOERROR);
        $this->assertCount(1, $document->querySelectorAll(Selector::parse(':is(.a) p')->forHtml()));
    }

    public function testALongListIsNotExpanded()
    {
        // 8^6 = 262,144 selectors were it expanded
        $selector = str_repeat(':is(.a, .b, .c, .d, .e, .f, .g, .h) ', 6).'p';
        $start = hrtime(true);
        $this->assertLessThan(2 * \strlen($selector), \strlen(Selector::parse($selector)->forHtml()));
        $this->assertLessThan(100000000, hrtime(true) - $start, 'Well under 100ms.');
    }

    public function testTheSpecificityIsTheOneOfTheSource()
    {
        // :is() takes its most specific argument: an id, and :where() nothing
        $this->assertSame(Selector::parse('#b p')->specificity, Selector::parse(':is(.a, #b) p')->specificity);
        $this->assertSame(Selector::parse('nav a p')->specificity, Selector::parse(':is(nav a, footer) p')->specificity);
        $this->assertSame(Selector::parse('p')->specificity, Selector::parse(':where(nav a) p')->specificity);
    }

    public function testInTheCascade()
    {
        $sheet = new CssStyleSheet();
        $view = (new HtmlBuilder($sheet))->build(<<<'HTML'
            <style>
              :is(nav section, footer) p { color: #ff0000 }
              :where(nav section) h2 { color: #0000ff }
              h2 { color: #111111 }
              :is(section:hover > div) p { color: #00ff00 }
            </style>
            <nav><section><div><p id="x">x</p></div><h2 id="h">h</h2></section></nav>
            <footer><div><p id="y">y</p></div></footer>
            <main><section id="s"><div><div><p id="z">z</p></div></div></section></main>
            HTML);
        $view->computeStyles();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('x'), 'color'));
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('y'), 'color'));
        $this->assertSame('#111111', $sheet->getComputedValue($view->getById('h'), 'color'), ':where() adds no specificity: the later rule wins.');
        $this->assertNull($sheet->getComputedValue($view->getById('z'), 'color'));
        $view->getById('s')->setState('hover', true);
        $view->computeStyles();
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('z'), 'color'), 'A state inside :is() before a combinator.');
    }

    public function testRulesNamingElementsTheDocumentHasNoneOfAreNotQueried()
    {
        // Matched from the right, :nth-child(2) would be computed for each of thousands of siblings
        $sheet = new CssStyleSheet();
        $view = (new HtmlBuilder($sheet))->build('<style>mroot > :nth-child(2) { color: #ff0000 } :is(ol, menu) > li:nth-last-child(1) { color: #00ff00 } p > em:nth-child(2) { color: #0000ff }</style><div>'.str_repeat('x<br>', 10000).'</div><ul><li id="u">u</li></ul><ol><li id="o">o</li></ol><p id="p">a <em>b</em> <em>c</em></p>');
        $start = microtime(true);
        $view->computeStyles();
        $this->assertLessThan(1.2, microtime(true) - $start, 'About 2 seconds if they were.');
        $this->assertNull($sheet->getComputedValue($view->getById('u'), 'color'));
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('o'), 'color'), 'One of the names of :is() is enough.');
        $this->assertStringContainsString("\x1b[38;2;0;0;255m\x1b[3mc", $view->getById('p')->getText(), 'An inline element, its name read too.');
        $this->assertStringNotContainsString("\x1b[38;2;0;0;255m\x1b[3mb", $view->getById('p')->getText());
    }

    public function testSelectorsWithEscapesAreAlwaysQueried()
    {
        // The space ending an escape is not a combinator, nor is what follows an escaped quote a type
        $sheet = new CssStyleSheet();
        $view = (new HtmlBuilder($sheet))->build('<style>#\31 st { color: #ff0000 } .\31 a { color: #00ff00 } [title="\"] b \""] { color: #0000ff }</style><p id="1st">x</p><p id="a" class="1a">y</p><p id="t" title=\'"] b "\'>z</p>');
        $view->computeStyles();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('1st'), 'color'));
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('a'), 'color'));
        $this->assertSame('#0000ff', $sheet->getComputedValue($view->getById('t'), 'color'));
    }

    public function testARuleMatchesOnceTheElementItNamesIsThere()
    {
        $sheet = new CssStyleSheet();
        $view = (new HtmlBuilder($sheet))->build('<style>aside p { color: #ff0000 }</style><main><p id="p">p</p></main>');
        $view->computeStyles();
        $this->assertNull($sheet->getComputedValue($view->getById('p'), 'color'));
        $aside = (new HtmlBuilder($sheet))->build('<aside><p id="q">q</p></aside>')->getBody()->all()[0];
        $view->getBody()->add($aside);
        $view->computeStyles();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('q'), 'color'));
    }

    #[DataProvider('provideStructuralQueries')]
    public function testStructuralQueriesKeepTheirSubjects(string $selector, array $ids)
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><section><span></span><p id="a" title="x > p"></p><p id="b"><em id="e"></em></p></section>', \LIBXML_NOERROR);
        $native = implode(', ', array_map(static fn (Selector $part) => $part->forHtml(), Selector::parseList($selector)));
        $elements = Selector::queryAll($document, $native);
        $this->assertSame($ids, array_map(static fn (\Dom\Element $element) => $element->id, iterator_to_array($elements, false)));
    }

    public static function provideStructuralQueries(): iterable
    {
        yield 'typed subject' => ['p:nth-child(2)', ['a']];
        yield 'ancestor' => ['section > p:nth-child(3)', ['b']];
        yield 'attribute subject' => ['[title="x > p"]:nth-child(2)', ['a']];
        yield 'quoted combinator' => ['p:nth-child(2):is([title="x > p"])', ['a']];
        yield 'functional ancestor' => [':is(section) > p:nth-child(2)', ['a']];
        yield 'selector list' => ['p:nth-child(2), em', ['a', 'e']];
        yield 'functional list' => [':is(p:nth-child(2), em)', ['a', 'e']];
        yield 'escaped uppercase type' => ['\50:nth-child(2)', ['a']];
    }

    public function testTypesMatchHtmlElementsInAnyCaseAndSvgOnesInTheirs()
    {
        $sheet = new CssStyleSheet();
        $view = (new HtmlBuilder($sheet))->build('<style>DIV P { color: #ff0000 }</style><div><p id="p">p</p></div>');
        $view->computeStyles();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('p'), 'color'));
        $builder = new HtmlBuilder();
        $builder->build('<style>#f feFlood { flood-color: #00ff00 } #f feflood { flood-opacity: .5 }</style><svg><filter id="f"><feFlood/></filter></svg>');
        $flood = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><filter id="f"><feFlood/></filter></svg>')->getElementsByTagName('feFlood')->item(0);
        $this->assertSame(['flood-color' => '#00ff00'], $builder->getStyleSheet()->svgDeclarations($flood, ['flood-color', 'flood-opacity']), 'An SVG element in its case only.');
    }
}
