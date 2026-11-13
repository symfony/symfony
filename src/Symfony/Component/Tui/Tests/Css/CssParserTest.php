<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssParser;
use Symfony\Component\Tui\Css\CssRule;
use Symfony\Component\Tui\Css\Declaration;
use Symfony\Component\Tui\Css\MediaQuery;
use Symfony\Component\Tui\Css\Selector;
use Symfony\Component\Tui\Css\StylesheetLoaderInterface;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Tests\Fixtures\CollectingLogger;

final class CssParserTest extends TestCase
{
    public function testRules()
    {
        $rules = (new CssParser())->parse('h1, .a { color: red; font-weight: bold } p{padding:1}');

        $this->assertSame(['h1', '.a', 'p'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
        $this->assertSame([1, 1, 2], array_map(static fn (CssRule $r) => $r->order, $rules));
        $this->assertSame([['color', 'red'], ['font-weight', 'bold']], self::declarations($rules[0]));
        $this->assertSame([['padding', '1']], self::declarations($rules[2]));
    }

    public function testComments()
    {
        $rules = (new CssParser())->parse('/* a { color: red } */ p { /* x */ content: "/* not a comment */"; color: blue }');

        $this->assertCount(1, $rules);
        $this->assertSame([['content', '"/* not a comment */"'], ['color', 'blue']], self::declarations($rules[0]));
    }

    public function testByteOrderMark()
    {
        // Like the style sheet of the documentation of Django, whose first rule was lost with its @import
        $rules = (new CssParser())->parse("\u{FEFF}@import url(x.css); html, body { color: red } p { color: blue }");

        $this->assertSame(['html', 'body', 'p'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
    }

    public function testEscapesInSelectors()
    {
        // The escaped quotes and parentheses of the class names of Tailwind are neither strings nor groups
        $rules = (new CssParser())->parse(".before\\:content-\\[\\'—\\'\\]:before { content: \"—\" } /* c */ .w-\\(--x\\) { width: 1ch } b { color: red }");

        $this->assertSame([".before\\:content-\\[\\'—\\'\\]:before", '.w-\\(--x\\)', 'b'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
        $this->assertSame([['color', 'red']], self::declarations($rules[2]));
    }

    public function testCommentsAroundALongString()
    {
        // A quoted span far longer than a pattern backtracks over, like those of a style sheet of Tailwind
        $rules = (new CssParser())->parse('/* a */ p { content: "'.str_repeat('x', 300_000).'" } /* b */ b { color: red }');

        $this->assertCount(2, $rules);
        $this->assertSame([['color', 'red']], self::declarations($rules[1]));
    }

    public function testMedia()
    {
        $rules = (new CssParser())->parse('@media (min-width: 80) { p { color: red } @media (prefers-color-scheme: light) { a { color: blue } } } b { color: green }');

        $this->assertCount(3, $rules);
        $this->assertCount(1, $rules[0]->media);
        $this->assertCount(2, $rules[1]->media);
        $this->assertSame([], $rules[2]->media);

        $wide = new MediaContext(100);
        $this->assertTrue($rules[1]->media[0]->matches($wide));
        $this->assertFalse($rules[1]->media[1]->matches($wide));
    }

    public function testCustomMedia()
    {
        $loader = new class implements StylesheetLoaderInterface {
            public function load(string $href): string
            {
                return '@custom-media --dark (prefers-color-scheme: dark); @media (--dark) and (--narrow) { em { color: red } }';
            }
        };
        $logger = new CollectingLogger();
        $parser = new CssParser($loader, $logger);

        $rules = $parser->parse('@media (--narrow) { p { color: red } } @custom-media --narrow (max-width: 40); @import "x.css" (--narrow); a { color: blue; @custom-media --nested (width); }');
        $later = $parser->parse('@media (--narrow) { b { color: red } }');

        $this->assertSame(['p', 'em', 'a'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
        $narrow = new MediaContext(30);
        $wide = new MediaContext(100);
        $this->assertTrue($rules[0]->media[0]->matches($narrow), 'A custom media query can be used before its definition.');
        $this->assertFalse($rules[0]->media[0]->matches($wide));
        $this->assertTrue($rules[1]->media[1]->matches($narrow), 'An imported sheet defines and uses custom media queries.');
        $this->assertTrue($later[0]->media[0]->matches($narrow), 'Custom media queries stay defined for the next style sheets.');
        $this->assertSame(['The "@custom-media" CSS at-rule is not supported in a style rule.'], $logger->messages);
    }

    public function testPositionTry()
    {
        $logger = new CollectingLogger();
        $parser = new CssParser(logger: $logger);

        $rules = $parser->parse('@position-try --Above { position-area: top; top: anchor(bottom) !important } @position-try nope { top: 0 } @position-try --above { left: 0 } p { color: red }');

        $this->assertCount(1, $rules);
        $this->assertSame(['--above' => ['left' => '0']], $parser->takePositionTries(), 'A later rule of a name replaces an earlier one.');
        $this->assertSame([], $parser->takePositionTries());
        $this->assertSame(['Invalid @position-try name "nope".'], $logger->messages);
    }

    public function testOuterMedia()
    {
        $rules = (new CssParser())->parse('p { color: red }', [MediaQuery::parse('print')]);

        $this->assertCount(1, $rules[0]->media);
    }

    public function testImport()
    {
        $loader = new class implements StylesheetLoaderInterface {
            public array $loaded = [];

            public function load(string $href): string
            {
                $this->loaded[] = $href;

                return match ($href) {
                    'base.css' => '@import "nested.css"; p { color: red }',
                    'nested.css' => 'em { color: blue }',
                    'self.css' => '@import url(self.css); i { color: red }',
                    default => throw new InvalidArgumentException(\sprintf('"%s" not found.', $href)),
                };
            }
        };
        $logger = new CollectingLogger();

        $rules = (new CssParser($loader, $logger))->parse('@import url("base.css") (min-width: 80); @import "self.css"; @import "missing.css"; a { color: green }');

        $this->assertSame(['em', 'p', 'i', 'a'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
        $this->assertCount(1, $rules[0]->media, 'The media of an import applies to its imports.');
        $this->assertCount(1, $rules[1]->media);
        $this->assertSame([], $rules[3]->media);
        $this->assertTrue($logger->has('"self.css" imports itself'));
        $this->assertTrue($logger->has('"missing.css" not found'));
    }

    public function testImportWithoutLoader()
    {
        $logger = new CollectingLogger();

        $this->assertSame([], (new CssParser(logger: $logger))->parse('@import "a.css";'));
        $this->assertTrue($logger->has('no stylesheet loader'));
    }

    public function testIgnoredAndUnknownAtRules()
    {
        $logger = new CollectingLogger();

        $rules = (new CssParser(logger: $logger))->parse('@charset "utf-8"; @font-face { font-family: x } @keyframes spin { from { color: red } to { color: blue } } @supports (display: grid-lanes) { p { color: red } } @unknown { p { color: red } } @foo bar; @namespace svg url(http://www.w3.org/2000/svg); @page :first { margin: 1in } @view-transition { navigation: auto } @font-palette-values --p { font-family: x } a { color: blue }');

        $this->assertSame(['a'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
        $this->assertSame(['The "@unknown" CSS at-rule is not supported.', 'The "@foo" CSS at-rule is not supported.'], $logger->messages);
    }

    public function testInvalidSelectorDropsOnlyItsRule()
    {
        $logger = new CollectingLogger();

        $rules = (new CssParser(logger: $logger))->parse('a { color: red } a! , b { color: blue } c { color: green }');

        $this->assertSame(['a', 'c'], array_map(static fn (CssRule $r) => $r->selector->native, $rules));
        $this->assertTrue($logger->has('Invalid selector "a! , b"'));
    }

    public function testNesting()
    {
        $rules = (new CssParser(strict: true))->parse('.a { color: red; .b { color: blue } &:focus { color: green } > p { color: cyan } .x & { color: white } font-weight: bold }');

        $this->assertSame(['.a', '.a .b', '.a:focus', '.a > p', '.x .a'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
        $this->assertSame([['color', 'red'], ['font-weight', 'bold']], self::declarations($rules[0]), 'Declarations after nested rules apply to the parent.');
        $this->assertSame([1, 2, 3, 4, 5], array_map(static fn (CssRule $r) => $r->order, $rules), 'Nested rules come after their parent.');
    }

    public function testNestingWrapsComplexOrListParentsInIs()
    {
        $rules = (new CssParser(strict: true))->parse('.a .b, .c { & + p { color: red } .d { color: blue } }');

        $this->assertSame([':is(.a .b, .c) + p', ':is(.a .b, .c) .d'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
        $this->assertSame(201, $rules[0]->selector->specificity, 'The parent counts with the specificity of its most specific selector.');
    }

    public function testNestingWithSelectorListsAndDepth()
    {
        $rules = (new CssParser(strict: true))->parse('.a { .b, .c { .d { color: red } } }');

        $this->assertSame([':is(.a .b, .a .c) .d'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
    }

    public function testNestedAtRules()
    {
        $rules = (new CssParser(strict: true))->parse('.a { @media (min-width: 80) { color: red; .b { color: blue } } @supports (color: red) { font-weight: bold } }');

        $this->assertSame(['.a', '.a .b', '.a'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
        $this->assertSame([1, 1, 0], array_map(static fn (CssRule $r) => \count($r->media), $rules));
        $this->assertSame([['font-weight', 'bold']], self::declarations($rules[2]));
    }

    public function testAmpersandInStringsIsKept()
    {
        $rules = (new CssParser(strict: true))->parse('.a { &[title="x & y"] { color: red } }');

        $this->assertSame(['.a[title="x & y"]'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
    }

    public function testInvalidNestedSelectorDropsOnlyItsRule()
    {
        $logger = new CollectingLogger();

        $rules = (new CssParser(logger: $logger))->parse('.a { color: red; &! { color: blue } .b { color: green } }');

        $this->assertSame(['.a', '.a .b'], array_map(static fn (CssRule $r) => $r->selector->source, $rules));
        $this->assertTrue($logger->has('Invalid selector "&!"'));
    }

    #[DataProvider('provideSupports')]
    public function testSupports(string $condition, bool $expected)
    {
        $rules = (new CssParser())->parse('@supports '.$condition.' { p { color: red } }');

        $this->assertCount($expected ? 1 : 0, $rules);
    }

    public static function provideSupports(): iterable
    {
        yield 'mapped' => ['(color: red)', true];
        yield 'invalid value' => ['(color: nonsense)', false];
        yield 'unknown property' => ['(foo: bar)', false];
        yield 'ignored property' => ['(zoom: 2)', false];
        yield 'custom property' => ['(--x: anything)', true];
        yield 'important' => ['(color: red !important)', true];
        yield 'currentcolor' => ['(border-color: currentcolor)', true];
        yield 'finalized gap' => ['(gap: 2ch)', true];
        yield 'invalid gap' => ['(gap: 10%)', false];
        yield 'alignment' => ['(align-items: center)', true];
        yield 'not' => ['not (display: grid-lanes)', true];
        yield 'and' => ['(color: red) and (display: flex)', true];
        yield 'and false' => ['(color: red) and (foo: bar)', false];
        yield 'or' => ['(foo: bar) or (color: red)', true];
        yield 'nested' => ['((color: red) and (not (foo: bar)))', true];
        yield 'selector' => ['selector(:has(> p))', true];
        yield 'invalid selector' => ['selector(a!)', false];
        yield 'unknown function' => ['font-tech(color-COLRv1)', false];
    }

    public function testUnclosedBlockEndsWithTheSheet()
    {
        $rules = (new CssParser())->parse('a { color: red');

        $this->assertSame([['color', 'red']], self::declarations($rules[0]));
    }

    public function testTrailingGarbage()
    {
        $logger = new CollectingLogger();

        $this->assertSame([], (new CssParser(logger: $logger))->parse('oops'));
        $this->assertTrue($logger->has('Unexpected "oops"'));
    }

    public function testDeclarations()
    {
        $logger = new CollectingLogger();

        $declarations = (new CssParser(logger: $logger))->parseDeclarations('COLOR: red !important; --Accent-Color: Blue; background: url(a;b); bad; : x; padding: ; --empty:;');

        $this->assertEquals([
            new Declaration('color', 'red', true),
            new Declaration('--Accent-Color', 'Blue'),
            new Declaration('background', 'url(a;b)'),
            new Declaration('--empty', ''),
        ], $declarations);
        $this->assertCount(3, $logger->messages);
    }

    public function testEmptyDeclarations()
    {
        // Nothing between two semicolons is no declaration: not an invalid one, which a strict parser throws for
        $declarations = (new CssParser(strict: true))->parseDeclarations(' ; color: red;; ;');

        $this->assertEquals([new Declaration('color', 'red')], $declarations);
    }

    public function testImportantWithSpaces()
    {
        $declarations = (new CssParser())->parseDeclarations('color: red ! IMPORTANT');

        $this->assertTrue($declarations[0]->important);
        $this->assertSame('red', $declarations[0]->value);
    }

    public function testStrict()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid selector');

        (new CssParser(strict: true))->parse('a! { color: red }');
    }

    public function testOrderContinuesAcrossCalls()
    {
        $parser = new CssParser();
        $parser->parse('a { color: red }');

        $this->assertSame(2, $parser->parse('b { color: red }')[0]->order);
    }

    public function testSupport()
    {
        $this->assertArrayHasKey('css.at-rules.import', CssParser::getSupport());
    }

    /**
     * @return list<array{string, string}>
     */
    private static function declarations(CssRule $rule): array
    {
        return array_map(static fn (Declaration $d) => [$d->property, $d->value], $rule->declarations);
    }

    public function testScope()
    {
        $rules = (new CssParser())->parse('@scope (.card) to (.body) { p { color: red } :scope > h3 { color: blue } p::before { content: "x" } } @scope { em { color: green } }', [], '[data-x]');

        $this->assertCount(4, $rules);
        $this->assertTrue($rules[0]->scoped);
        $this->assertSame(Selector::parse('p')->specificity, $rules[0]->selector->specificity, 'The root and the limit add no specificity.');
        $this->assertStringContainsString(':where(.card) > h3', $rules[1]->selector->source, ':scope is the root.');
        $this->assertSame('before', $rules[2]->selector->pseudoElement, 'The limit goes before the pseudo-element.');
        $this->assertStringContainsString(':where([data-x]) em', $rules[3]->selector->source, 'Without a root, the element the style is in.');
    }
}
