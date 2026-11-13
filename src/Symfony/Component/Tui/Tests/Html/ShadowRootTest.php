<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\Selector;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class ShadowRootTest extends TestCase
{
    #[DataProvider('provideSelectors')]
    public function testSelectorsMatchInTheirTree(string $selector, int $tree, string $expected)
    {
        $this->assertSame($expected, Selector::inTree($selector, $tree));
    }

    public static function provideSelectors(): iterable
    {
        yield 'a rule of the document: outside the trees, its ancestors too' => ['main p', 0, 'main:not([data-tui-tree]) p:not([data-tui-tree])'];
        yield 'a rule of a tree: in it, or its host' => ['.title > b', 2, '.title:is([data-tui-host="2"], [data-tui-tree="2"]) > b:is([data-tui-host="2"], [data-tui-tree="2"])'];
        yield 'before a pseudo-element' => ['p::before', 2, 'p:is([data-tui-host="2"], [data-tui-tree="2"])::before'];
        yield ':host' => [':host', 1, '[data-tui-host="1"]:is([data-tui-host="1"], [data-tui-tree="1"])'];
        yield ':host() and a descendant' => [':host(.dark) p', 1, '[data-tui-host="1"]:is(.dark):is([data-tui-host="1"], [data-tui-tree="1"]) p:is([data-tui-host="1"], [data-tui-tree="1"])'];
        yield ':host-context(): ancestors anywhere' => [':host-context(.page)', 1, ':is(.page):not([data-tui-context]) [data-tui-host="1"]:is([data-tui-host="1"], [data-tui-tree="1"])'];
        yield '::slotted()' => ['::slotted(span)', 1, '[data-tui-slotted="1"]:is(span)'];
        yield '::slotted() of a slot' => ['slot[name=a]::slotted(*)', 1, 'slot[name=a]:is([data-tui-host="1"], [data-tui-tree="1"]) > [data-tui-slotted="1"]:is(*)'];
        yield '::slotted() of a slot in an element' => ['section ::slotted(div)', 1, 'section:is([data-tui-host="1"], [data-tui-tree="1"]) *:is([data-tui-host="1"], [data-tui-tree="1"]) > [data-tui-slotted="1"]:is(div)'];
        yield ':host() before ::slotted()' => [':host(.d) ::slotted(div)', 1, '[data-tui-host="1"]:is(.d):is([data-tui-host="1"], [data-tui-tree="1"]) *:is([data-tui-host="1"], [data-tui-tree="1"]) > [data-tui-slotted="1"]:is(div)'];
        yield '::part()' => ['my-card::part(title)', 0, 'my-card:not([data-tui-tree]) [data-tui-part-0~="title"]'];
        yield 'an escape is not a combinator' => ['#\\31 st', 1, '#\\31 st:is([data-tui-host="1"], [data-tui-tree="1"])'];
        yield ':host in :is(): not the page' => [':is(:host, .x)', 1, ':is([data-tui-host="1"], .x):is([data-tui-host="1"], [data-tui-tree="1"])'];
    }

    public function testTheShadowTreeIsDrawnWithTheLightChildrenInItsSlots()
    {
        $this->assertSame(['┌──────────┐', '│Title     │', '│light     │', '│inner     │', '└──────────┘', 'none'], $this->render(<<<'HTML'
            <x-card>
              <template shadowrootmode="open">
                <style>:host { border: 1px solid; width: 12ch }</style>
                <slot name="title">No title</slot><div><slot>Fallback</slot></div><p>inner</p>
              </template>
              <b slot="title">Title</b><span>light</span><i slot="unknown">left out</i>
            </x-card>
            <x-empty><template shadowrootmode="closed"><slot>none</slot></template></x-empty>
            HTML));
    }

    public function testTheRulesOfEachTreeMatchInItOnly()
    {
        $html = <<<'HTML'
            <style>p { color: #00ff00 } x-card::part(label) { font-weight: bold }</style>
            <x-card class="dark">
              <template shadowrootmode="open">
                <style>p { color: #ff0000 } :host(.dark) { color: #0000ff } ::slotted(p) { text-decoration: underline }</style>
                <p part="label" id="inner">inner</p><slot></slot><div id="inherited">inherited</div>
              </template>
              <p id="light">light</p>
            </x-card>
            HTML;
        $builder = (new HtmlBuilder())->setDefaultStyles(false);
        $view = $builder->build($html)->computeStyles();
        $sheet = $builder->getStyleSheet();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('inner'), 'color'), 'The rule of the tree, not of the document.');
        $this->assertSame('bold', $sheet->getComputedValue($view->getById('inner'), 'font-weight'), 'A part, from the document.');
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('light'), 'color'), 'A light element: the rules of the document...');
        $this->assertSame('underline', $sheet->getComputedValue($view->getById('light'), 'text-decoration'), '...and ::slotted() of the tree.');
        $this->assertSame('#0000ff', $sheet->getComputedValue($view->getById('inherited'), 'color'), 'Inherited from the host, through the tree.');
    }

    public function testThePageWinsOverTheTreeOnItsHostButForImportantDeclarations()
    {
        $html = <<<'HTML'
            <style>x-card { color: #00ff00 } x-card { background-color: #222222 } p { font-style: normal }</style>
            <x-card id="host">
              <template shadowrootmode="open">
                <style>:host(#host.more.specific) { color: #ff0000 } :host { background-color: #333333 !important } ::slotted(p) { font-style: italic }</style>
                <slot></slot>
              </template>
              <p id="light" class="x">light</p>
            </x-card>
            HTML;
        $builder = (new HtmlBuilder())->setDefaultStyles(false);
        $view = $builder->build(str_replace('id="host"', 'id="host" class="more specific"', $html))->computeStyles();
        $sheet = $builder->getStyleSheet();
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('host'), 'color'), 'Whatever the specificity of :host().');
        $this->assertSame('#333333', $sheet->getComputedValue($view->getById('host'), 'background-color'), 'Important: the tree wins.');
        $this->assertSame('normal', $sheet->getComputedValue($view->getById('light'), 'font-style'), 'The page over ::slotted().');
    }

    public function testASlotIsInTheLineOfWhatItHolds()
    {
        $this->assertSame(['Name: Bob!'], $this->render('<div><template shadowrootmode="open">Name: <slot name="n">?</slot>!</template><b slot="n">Bob</b></div>'));
    }

    public function testOnlyTheFirstValidTemplateOfAHostIsItsShadowRoot()
    {
        $this->assertSame(['first L'], $this->render('<x-a><template shadowrootmode="open">first <slot></slot></template><template shadowrootmode="open">second</template><b>L</b></x-a>'), 'The second, not drawn, nor slotted.');
        $this->assertSame(['light'], $this->render('<div><template shadowrootmode="bogus">S</template><p>light</p></div>'), 'Not a mode.');
        $this->assertSame(['• item'], $this->render('<ul><template shadowrootmode="open">S</template><li>item</li></ul>'), 'Not an element that can have one.');
    }

    public function testTheRulesOfATreeDoNotReachThePage()
    {
        $html = <<<'HTML'
            <div class="page"><x-a id="host"><template shadowrootmode="open">
              <style>.page div { color: #ff0000 } :is(:host, .x) { font-weight: bold } :host(#host) ::slotted(p) { font-style: italic } section ::slotted(p) { text-decoration: underline }</style>
              <div id="in">in</div><section><div><slot></slot></div></section>
            </template><p id="light">light</p></x-a></div>
            <p class="x" id="x">page</p>
            <x-b><template shadowrootmode="open"><style>#d { text-align: left }</style><div id="d" align="center">x</div></template></x-b>
            HTML;
        $builder = (new HtmlBuilder())->setDefaultStyles(false);
        $view = $builder->build($html)->computeStyles();
        $sheet = $builder->getStyleSheet();
        $this->assertNull($sheet->getComputedValue($view->getById('in'), 'color'), 'Its ancestors in it, or its host.');
        $this->assertSame('bold', $sheet->getComputedValue($view->getById('host'), 'font-weight'));
        $this->assertNull($sheet->getComputedValue($view->getById('x'), 'font-weight'), 'Not the page through :is().');
        $this->assertSame('italic', $sheet->getComputedValue($view->getById('light'), 'font-style'), ':host() before ::slotted().');
        $this->assertSame('underline', $sheet->getComputedValue($view->getById('light'), 'text-decoration'), 'A slot inside an element of it.');
        $this->assertSame('left', $sheet->getComputedValue($view->getById('d'), 'text-align'), 'Its rules over the presentational hints.');
    }

    public function testThePartsOfNestedTreesAreThoseTheirHostsExport()
    {
        $html = <<<'HTML'
            <style>x-outer::part(own) { color: #ff0000 } x-outer::part(icon) { color: #00ff00 } x-outer::part(renamed) { color: #0000ff } x-outer::part(hidden) { font-weight: bold }</style>
            <x-outer><template shadowrootmode="open">
              <div part="own" id="own">own</div>
              <x-inner exportparts="icon, label: renamed"><template shadowrootmode="open">
                <div part="icon" id="icon">icon</div><div part="label" id="label">label</div><div part="hidden" id="hidden">hidden</div>
              </template></x-inner>
            </template></x-outer>
            HTML;
        $builder = (new HtmlBuilder())->setDefaultStyles(false);
        $view = $builder->build($html)->computeStyles();
        $sheet = $builder->getStyleSheet();
        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('own'), 'color'));
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('icon'), 'color'), 'Exported.');
        $this->assertSame('#0000ff', $sheet->getComputedValue($view->getById('label'), 'color'), 'Exported under another name.');
        $this->assertNull($sheet->getComputedValue($view->getById('hidden'), 'font-weight'), 'Not exported: not a part of the outer tree.');
    }

    public function testATreeInATree()
    {
        // Hosts of inline content, and slots of text: in the line, like in browsers
        $this->assertSame(['outer: inner: light'], $this->render(<<<'HTML'
            <x-outer>
              <template shadowrootmode="open">outer: <x-inner><template shadowrootmode="open">inner: <slot></slot></template><slot></slot></x-inner></template>
              light
            </x-outer>
            HTML));
    }

    /**
     * @return list<string>
     */
    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(30, 8);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p, div { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 8);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
