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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\TuiHtml\Css\Selector;

final class SelectorTest extends TestCase
{
    #[DataProvider('provideSelectors')]
    public function testParse(string $source, string $native, int $specificity, ?string $pseudoElement, bool $dynamic)
    {
        $selector = Selector::parse($source);
        $this->assertSame($native, $selector->native);
        $this->assertSame($specificity, $selector->specificity);
        $this->assertSame($pseudoElement, $selector->pseudoElement);
        $this->assertSame($dynamic, $selector->dynamic);
    }

    public static function provideSelectors(): iterable
    {
        yield 'type' => ['div', 'div', 1, null, false];
        yield 'type keeps its case, matched in any for HTML' => ['DIV', 'DIV', 1, null, false];
        yield 'an SVG type in its case' => ['feFlood', 'feFlood', 1, null, false];
        yield 'universal' => ['*', '*', 0, null, false];
        yield 'class' => ['.card', '.card', 100, null, false];
        yield 'id' => ['#main', '#main', 10000, null, false];
        yield 'attribute' => ['[data-x="a,b"]', '[data-x="a,b"]', 100, null, false];
        yield 'compound' => ['div.a.b#c', 'div.a.b#c', 10201, null, false];
        yield 'a hex escape ends at the whitespace after it' => ['.max-w-\[calc\(min\(700px\2c 100\%\)\)\]', '.max-w-\[calc\(min\(700px\2c 100\%\)\)\]', 100, null, false];
        yield 'any namespace' => ['*|p', 'p', 1, null, false];
        yield 'any namespace, any element' => ['*|*', '*', 0, null, false];
        yield 'undeclared namespace' => ['svg|a', 'a:not(*)', 1, null, false];
        yield 'no namespace' => ['|p', 'p:not(*)', 1, null, false];
        yield 'namespace after a combinator' => ['div > svg|*', 'div > *:not(*)', 1, null, false];
        yield 'ignored functional pseudo-element' => ['p::part(label)', 'p', 2, 'part', false];
        yield 'ignored functional pseudo-class' => [':host(.x)', '[data-tui-state~="host"]', 100, null, true];
        yield 'combinators' => ['main > .a + p ~ span', 'main > .a + p ~ span', 103, null, false];
        yield 'focus' => ['input:focus', 'input[data-tui-state~="focus"]', 101, null, true];
        yield 'focus-visible is focus' => [':focus-visible', '[data-tui-state~="focus"]', 100, null, true];
        yield 'focus-within' => ['.card:focus-within .x', '.card[data-tui-state~="focus-within"] .x', 300, null, true];
        yield 'custom state' => ['.btn:loading', '.btn[data-tui-state~="loading"]', 200, null, true];
        yield 'enabled' => ['a:enabled', 'a:not([data-tui-state~="disabled"])', 101, null, true];
        yield 'read-only' => ['p:read-only', 'p:not([data-tui-state~="read-write"])', 101, null, true];
        yield 'condensed' => [':condensed', '[data-tui-state~="condensed"]', 100, null, true];
        yield 'structural' => ['li:first-child', 'li:first-child', 101, null, false];
        yield 'root' => [':root', ':root', 100, null, false];
        yield 'nth-child' => ['li:nth-child(2n+1)', 'li:nth-child(2n+1)', 101, null, false];
        yield 'nth-child of S' => ['li:nth-child(2n+1 of .y)', 'li:nth-child(2n+1 of .y)', 201, null, false];
        yield 'is takes the max' => [':is(#a, .b) p', '*:is(#a, .b) p', 10001, null, false];
        yield 'not takes the max' => ['p:not(.a, .b.c)', 'p:not(.a, .b.c)', 201, null, false];
        yield 'where counts nothing' => [':where(#x, .y) p', '*:where(#x, .y) p', 1, null, false];
        yield 'matches is is' => [':matches(.a)', '*:is(.a)', 100, null, false];
        yield 'has relative' => ['div:has(> p:hover)', 'div:has(> p[data-tui-state~="hover"])', 102, null, true];
        yield 'nested state' => ['p:not(:focus)', 'p:not([data-tui-state~="focus"])', 101, null, true];
        yield 'pseudo-element' => ['tui-tabs::tab-active', 'tui-tabs', 2, 'tab-active', false];
        yield 'pseudo-element with state' => ['select::selected:focus', 'select[data-tui-state~="focus"]', 102, 'selected', true];
        yield 'class pseudo-element' => ['.list::selected', '.list', 101, 'selected', false];
        yield 'pseudo-element of CSS 2, with one colon' => ['td:before', 'td', 2, 'before', false];
        yield 'another of CSS 2' => ['p.intro:first-letter', 'p.intro', 102, 'first-letter', false];
        yield 'bare pseudo-element' => ['::selected', '*', 1, 'selected', false];
        yield 'lang' => ['p:lang(en)', 'p:is([data-tui-lang|="en" i])', 101, null, false];
        yield 'lang list' => [':lang(en, "fr")', '*:is([data-tui-lang|="en" i], [data-tui-lang|="fr" i])', 100, null, false];
        yield 'lang wildcard' => [':lang(*)', '*:is([data-tui-lang])', 100, null, false];
        yield 'dir rtl' => [':dir(rtl)', '[data-tui-dir="rtl"]', 100, null, false];
        yield 'dir ltr' => ['p:dir(ltr)', 'p:not([data-tui-dir="rtl"])', 101, null, false];
        yield 'scope' => [':scope > p', ':root > p', 101, null, false];
        yield 'any-link' => ['div:has(:any-link)', 'div:has(*:is(a[href], area[href]))', 101, null, false];
        yield 'link' => [':link', '*:is(a[href], area[href]):not([data-tui-state~="visited"])', 100, null, true];
        yield 'paused' => ['video:paused', 'video:is(video, audio)', 101, null, false];
        yield 'visited' => ['a:visited', 'a:is(a[href], area[href])[data-tui-state~="visited"]', 101, null, true];
        yield 'details-content' => ['details::details-content', 'details > [data-tui-details-content]', 2, null, false];
        yield 'picker' => ['select::picker(select)', 'select > select', 2, null, false];
        yield 'picker with a state' => ['.x::picker(select):hover', '.x > select[data-tui-state~="hover"]', 201, null, true];
        yield 'heading' => [':heading', '*:is(h1, h2, h3, h4, h5, h6)', 100, null, false];
        yield 'heading levels' => ['section :heading(2, 3)', 'section *:is(h2, h3)', 101, null, false];
        yield 'heading past 6' => [':heading(7)', '*:is(h7)', 100, null, false];
    }

    public function testParseList()
    {
        $selectors = Selector::parseList('h1, .a:is(.b, .c), [title="x,y"]');
        $this->assertSame(['h1', '.a:is(.b, .c)', '[title="x,y"]'], array_map(static fn (Selector $s) => $s->native, $selectors));
        $this->assertSame([1, 200, 100], array_map(static fn (Selector $s) => $s->specificity, $selectors));
    }

    public function testRewrittenSelectorsMatchNatively()
    {
        $document = \Dom\HTMLDocument::createFromString('<div class="a"><input data-tui-state="root focus"><p>x</p></div>', \LIBXML_NOERROR);
        $this->assertCount(1, $document->querySelectorAll(Selector::parse('div.a > input:focus')->native));
        $this->assertCount(1, $document->querySelectorAll(Selector::parse('div:has(> :focus)')->native));
        $this->assertCount(0, $document->querySelectorAll(Selector::parse('p:focus')->native));
    }

    public function testStateSelector()
    {
        $this->assertSame('[data-tui-state~="hover"]', Selector::stateSelector('hover'));
    }

    #[DataProvider('provideInvalidSelectors')]
    public function testInvalid(string $selector)
    {
        $this->expectException(InvalidArgumentException::class);
        Selector::parse($selector);
    }

    public static function provideInvalidSelectors(): iterable
    {
        yield 'empty' => [''];
        yield 'dangling class' => ['.'];
        yield 'dangling pseudo' => ['a:'];
        yield 'unclosed attribute' => ['[a'];
        yield 'unclosed parenthesis' => [':is(.a'];
        yield 'unterminated string' => ['[a="b]'];
        yield 'unknown functional' => [':nonsense(fr)'];
        yield 'invalid language' => [':lang(e n)'];
        yield 'empty language' => [':lang()'];
        yield 'invalid direction' => [':dir(up)'];
        yield 'invalid heading level' => [':heading(h2)'];
        yield 'picker of another element' => ['select::picker(input)'];
        yield 'other functional pseudo-element' => ['p::nonsense(x)'];
        yield 'type after picker' => ['select::picker(select) p'];
        yield 'pseudo-element after picker' => ['select::picker(select)::selected'];
        yield 'zero heading level' => [':heading(0)'];
        yield 'empty heading' => [':heading()'];
        yield 'logical without argument' => [':not'];
        yield 'two pseudo-elements' => ['a::b::c'];
        yield 'type after pseudo-element' => ['a::b p'];
        yield 'nested pseudo-element' => [':is(a::b)'];
        yield 'unexpected character' => ['a!'];
    }

    public function testSplitTopLevel()
    {
        $this->assertSame(['a', ':is(b, c)', '"d,e"'], Selector::splitTopLevel('a, :is(b, c), "d,e"'));
        $this->assertSame(['a: b', 'c: url(x;y)'], Selector::splitTopLevel('a: b; c: url(x;y);', ';'));
        // Escaped quotes and brackets, like the classes of Tailwind, are no strings nor groups
        $this->assertSame(['.content-\[\\\'\\\'\] p', '.b\( q'], Selector::splitTopLevel('.content-\[\\\'\\\'\] p,.b\( q'));
    }

    public function testSupportListsTheStructuralPseudoClasses()
    {
        $support = Selector::getSupport();
        $this->assertArrayHasKey('css.selectors.nth-child', $support);
        $this->assertArrayHasKey('css.selectors.has', $support);
        $this->assertArrayNotHasKey('css.selectors.matches', $support);
    }
}
