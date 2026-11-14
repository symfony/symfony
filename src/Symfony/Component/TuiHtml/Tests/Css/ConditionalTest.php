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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\Conditional;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class ConditionalTest extends TestCase
{
    #[DataProvider('provideValues')]
    public function testResolve(string $value, string $expected)
    {
        // media(wide) and style(dark) pass, the other tests fail
        $test = static fn (string $name, string $argument) => \in_array($name.':'.$argument, ['media:wide', 'style:dark', 'supports:display: grid'], true);
        $this->assertSame($expected, Conditional::resolve($value, $test));
    }

    public static function provideValues(): iterable
    {
        yield 'no if' => ['1ch solid', '1ch solid'];
        yield 'first branch' => ['if(media(wide): 2ch; else: 1ch)', '2ch'];
        yield 'else' => ['if(media(narrow): 2ch; else: 1ch)', '1ch'];
        yield 'later branch' => ['if(media(narrow): a; style(dark): b; else: c)', 'b'];
        yield 'not' => ['if(not media(narrow): a; else: b)', 'a'];
        yield 'and' => ['if(media(wide) and style(dark): a; else: b)', 'a'];
        yield 'and failing' => ['if(media(wide) and style(light): a; else: b)', 'b'];
        yield 'or' => ['if(media(narrow) or style(dark): a; else: b)', 'a'];
        yield 'parentheses' => ['if((media(narrow) or style(dark)) and not media(narrow): a; else: b)', 'a'];
        yield 'supports with a declaration' => ['if(supports(display: grid): grid; else: flex)', 'grid'];
        yield 'unknown test is false' => ['if(foo(bar): a; else: b)', 'b'];
        yield 'empty value' => ['if(media(wide): ; else: b)', ''];
        yield 'in a value' => ['1px if(style(dark): solid; else: dashed) red', '1px solid red'];
        yield 'nested in the value' => ['if(media(wide): if(style(light): a; else: b); else: c)', 'b'];
        yield 'twice' => ['if(media(wide): a; else: b) if(media(narrow): c; else: d)', 'a d'];
        yield 'not a word ending' => ['notif(x)', 'notif(x)'];
        yield 'in a string' => ['"if(ready)" if(media(wide): a; else: b) \'if(x)\'', '"if(ready)" a \'if(x)\''];
    }

    public function testNoBranchPassingIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        Conditional::resolve('if(media(narrow): a)', static fn () => false);
    }

    public function testInStyleSheets()
    {
        $view = (new HtmlBuilder())->build(<<<'HTML'
            <style>
              .card { --variant: primary; padding-left: if(media(width < 50): 1ch; else: 4ch) }
              .card p { color: if(style(--variant: primary): blue; else: gray); background: if(supports(color: oklch(0.5 0.1 200)): green; else: red) }
              .card .other { color: if(style(--variant: secondary): red) }
              div { color: yellow }
            </style>
            <div class="card" id="card"><p id="p">a</p><div><p class="other" id="other">b</p></div></div>
            HTML);
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(null, $terminal);
        $view->mount($tui);
        $tui->start();
        $sheet = $view->getStyleSheet();
        $tui->processRender();
        $this->assertSame(4, $sheet->getStyles($view->getById('card'))[0]->getPadding()->left);
        $this->assertSame('blue', $this->color($sheet->getStyles($view->getById('p'))[0]->getColor()?->toForegroundCode()));
        $this->assertSame('green', $this->color($sheet->getStyles($view->getById('p'))[0]->getBackground()?->toBackgroundCode()));
        $this->assertSame('if(style(--variant: secondary): red)', $sheet->getComputedValue($view->getById('other'), 'color'), 'Declared, invalid when computed.');
        $terminal->simulateResize(40, 24);
        $tui->processRender();
        $this->assertSame(1, $sheet->getStyles($view->getById('card'))[0]->getPadding()->left, 'media() follows the media.');
    }

    private function color(?string $code): string
    {
        return match ($code) {
            "\x1b[34m", "\x1b[44m" => 'blue',
            "\x1b[32m", "\x1b[42m" => 'green',
            default => (string) $code,
        };
    }
}
