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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

/**
 * A class given to an element through its widget: what the rules match again is the element, and the siblings after it
 * that a selector reaches through + and ~, what they hold too when a selector goes on into it; drawn as the page built
 * with the class.
 */
final class ScopedRestyleTest extends TestCase
{
    private const COLUMNS = 40;
    private const ROWS = 90;

    public static function provideRules(): iterable
    {
        yield 'its own rule' => ['.x { color: red }'];
        yield 'a descendant' => ['.x em { color: red }'];
        yield 'the next sibling' => ['.x + p { color: red }'];
        yield 'what the next sibling holds' => ['.x + p em { color: red } .x + p + div > p { font-weight: bold }'];
        yield 'a sibling further on' => ['.x ~ p { color: red }'];
        yield 'what a sibling further on holds' => ['.x ~ div em { color: red }'];
        yield 'two siblings on' => ['.x + p + div { color: red }'];
        yield 'in :is()' => [':is(.x + p) em { color: red }'];
        yield 'counted among its siblings' => [':nth-child(2 of .x) { color: red } :nth-last-child(1 of .x) { font-weight: bold }'];
        yield 'from its parent' => ['div:has(> .x) { color: red }'];
        yield 'from a sibling before it' => ['p:has(+ .x) { color: red }'];
        // A combinator into a sibling, then another sibling combinator
        yield 'a sibling of what a sibling two on holds' => ['.x + p + div p + span { color: red }'];
        yield 'a sibling further on of what a sibling holds' => ['.x ~ div p ~ span { color: red }'];
        yield 'an item after another in a list further on' => ['.x ~ ul li + li { color: red }'];
        yield 'a span after a child of a sibling two on' => ['.x + p + div > p ~ span { color: red }'];
        yield 'two siblings on, none held' => ['.x + .y + .z { color: red }'];
        // The descendant combinator written with any CSS whitespace, and compounds of one simple selector after it
        yield 'tabs' => [".x\t+\tp\tem { color: red }"];
        yield 'newlines' => [".x +\n\tp\n\tem { color: red }"];
        yield 'an attribute selector held' => ['.x + p [title] { color: red }'];
        yield 'an attribute selector held, then a sibling' => ['.x + p + div [class] + span { color: red }'];
        yield 'a universal selector held' => ['.x + p * { color: red }'];
        yield 'a pseudo-class held' => ['.x + p :first-child { color: red }'];
        yield ':is() held' => ['.x + p :is(em) { color: red }'];
        yield 'a pseudo-element held' => ['.x + p ::before { content: "»" }'];
        // A sibling combinator in the selector of :nth-*(), its parentheses nested, and escaped parentheses
        yield 'a sibling combinator in of S' => ['p:nth-child(1 of .x + p) em { color: red }'];
        yield 'nested parentheses in of S' => [':nth-child(1 of :is(.a, .b)) + .x + p em { color: red } .x + p em { font-weight: bold }'];
        yield 'nested parentheses in of S, then a sibling' => ['.x + p:nth-child(n of :is(p, div)) em { color: red }'];
        yield 'an escaped parenthesis' => ['.x\\) + p em { color: red }', 'x)'];
        yield 'an escaped opening parenthesis' => ['.x\\( + p em { color: red }', 'x('];
        // An escaped quote, not the start of a string, a string later in the selector
        yield 'an escaped quote, then a string' => ['.it\\\'s + p em[title=\'t\'] { color: red }', "it's"];
        yield 'an escaped quote, a sibling further on, then a string' => ['.it\\\'s ~ div:not([title=\'x\']) { color: red }', "it's"];
        yield 'a marker reached' => ['.x + ul li::marker { color: red } .x ~ ul > li { font-style: italic }'];
    }

    #[DataProvider('provideRules')]
    public function testAClassGivenIsDrawnAsThePageBuiltWithIt(string $css, string $given = 'x')
    {
        // Many items after it, so that matching a part of the page costs less than all of it
        $items = implode('', array_map(static fn (int $i): string => "<li>Item <em>$i</em></li>", range(1, 60)));
        $page = static fn (string $class): string => '<style>'.$css.'</style><div id="box"><p>Before</p><p id="p" class="'.htmlspecialchars($class).'">The <em>one</em></p><p>Next <em title="t">one</em></p><div class="y"><p class="in">In a <em>block</em></p><span>A span</span><p>Then</p><span>Another</span></div><p class="z">Zed</p><p class="x">Other</p><ul>'.$items.'</ul></div><p>After</p>';
        foreach ([['', $given], [$given, '']] as [$from, $to]) {
            [$tui, $terminal, $view] = $this->mount($page($from));
            $view->getById('p')->setStyleClasses('' === $to ? [] : [$to]);
            $this->render($tui);
            [$whole, $wholeTerminal] = $this->mount($page($to));

            $this->assertSame(self::screen($wholeTerminal), self::screen($terminal), "From \"$from\" to \"$to\".");
        }
    }

    public function testAnAttributeValueLikeOfSDoesNotMatchAllAgain()
    {
        // Strings that look like :nth-child(of S), in a rule of every state and in a rule of a state: neither counts
        // the siblings of a selector
        $items = implode('', array_map(static fn (int $i): string => "<li>Item $i</li>", range(1, 60)));
        $css = 'a[title=":nth-child(1 of x)"] + p { color: red } a[title=":nth-last-child(1 of y)"]:hover { color: blue }';
        [$tui, , $view] = $this->mount('<style>'.$css.'</style><div><p>Before</p><p id="p">The one</p><a title="t">A</a><p>Next</p><ul>'.$items.'</ul></div>');
        $stats = static fn (): array => (fn (): array => [$this->scopedStats, $this->dynamicStats])->call($view->getStyleSheet());
        [$scoped, $dynamic] = $stats();

        $view->getById('p')->setStyleClasses(['x']);
        $this->render($tui);

        [$scopedAfter, $dynamicAfter] = $stats();
        $this->assertSame([$scoped[0] + 1, $scoped[1]], $scopedAfter, 'Matched again in part, not all of it.');
        $this->assertSame([$dynamic[0] + 1, $dynamic[1]], $dynamicAfter, 'The rules of a state matched again in part too.');
    }

    /**
     * @return array{Tui, VirtualTerminal, HtmlView}
     */
    private function mount(string $html): array
    {
        $terminal = new VirtualTerminal(self::COLUMNS, self::ROWS);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $this->render($tui);

        return [$tui, $terminal, $view];
    }

    private function render(Tui $tui): void
    {
        $tui->requestRender();
        $tui->processRender();
    }

    private static function screen(VirtualTerminal $terminal): string
    {
        $screen = new ScreenBuffer(self::COLUMNS, self::ROWS);
        $screen->write($terminal->getOutput());

        return $screen->getStyledScreen();
    }
}
