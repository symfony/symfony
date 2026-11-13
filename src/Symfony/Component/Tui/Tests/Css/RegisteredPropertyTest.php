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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class RegisteredPropertyTest extends TestCase
{
    private float $now = 100.0;

    public function testInitialValueAndInheritance()
    {
        $view = $this->build(<<<'CSS'
            @property --accent { syntax: '<color>'; inherits: true; initial-value: #ff0000 }
            @property --local { syntax: '<color>'; inherits: false; initial-value: #00ff00 }
            .box { --local: #0000ff; --plain: #123456 }
            p { color: var(--accent); background: var(--local) }
            CSS, '<div class="box" id="box"><p id="p">text</p></div>');
        $sheet = $view->getStyleSheet();
        $p = $view->getById('p');

        $this->assertSame('#ff0000', $sheet->getComputedValue($p, '--accent'), 'The initial value, set nowhere.');
        $this->assertSame('#0000ff', $sheet->getComputedValue($view->getById('box'), '--local'));
        $this->assertSame('#00ff00', $sheet->getComputedValue($p, '--local'), 'Not inherited: the initial value again.');
        $this->assertSame('#123456', $sheet->getComputedValue($p, '--plain'), 'Unregistered ones inherit.');
    }

    public function testInvalidRegistrationsAreIgnored()
    {
        $view = $this->build(<<<'CSS'
            @property --a { syntax: '<color>'; initial-value: red }
            @property --b { syntax: '<color>'; inherits: false }
            @property --c { syntax: '*'; inherits: false }
            @property c { syntax: '*'; inherits: true; initial-value: red }
            CSS, '<div id="box"><p id="p">text</p></div>');
        $sheet = $view->getStyleSheet();

        $this->assertNull($sheet->getComputedValue($view->getById('p'), '--a'), 'No inherits descriptor.');
        $this->assertNull($sheet->getComputedValue($view->getById('p'), '--b'), 'No initial value for a typed syntax.');
    }

    public function testValuesAreCheckedAgainstTheSyntax()
    {
        $view = $this->build(<<<'CSS'
            @property --tone { syntax: '<color>'; inherits: true; initial-value: #ff0000 }
            @property --gap { syntax: '<length> | auto'; inherits: false; initial-value: auto }
            @property --list { syntax: '<number>+'; inherits: false; initial-value: 1 2 }
            @property --bad { syntax: '<color>'; inherits: false; initial-value: 10px }
            .box { --tone: #00ff00; --gap: 2ch }
            .box p { --tone: 10ch; --gap: blue; --list: 1 x 3; --bad: red }
            .box .ok { --gap: auto; --list: 3 4.5 }
            .box .via { --tone: var(--missing, #0000ff) }
            CSS, '<div class="box" id="box"><p id="p">a</p><p id="ok" class="ok">b</p><p id="via" class="via">c</p></div>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);

        $this->assertSame('#00ff00', $value('p', '--tone'), 'Not a color: invalid, it inherits.');
        $this->assertSame('auto', $value('p', '--gap'), 'Not a length nor auto, and not inherited: the initial value.');
        $this->assertSame('1 2', $value('p', '--list'), 'One of the list is not a number.');
        $this->assertSame('auto', $value('ok', '--gap'));
        $this->assertSame('3 4.5', $value('ok', '--list'));
        $this->assertSame('var(--missing, #0000ff)', $value('via', '--tone'), 'Checked once substituted: a color.');
        $this->assertSame('red', $value('p', '--bad'), 'An initial value not of the syntax: not registered, any value goes.');
    }

    public function testATypedCustomPropertyTransitions()
    {
        $sheet = new CssStyleSheet();
        $sheet->setAnimationHandlers(null, fn (): float => $this->now);
        $view = (new HtmlBuilder($sheet))->build('<style>
            @property --tone { syntax: "<color>"; inherits: true; initial-value: #000000 }
            p { --tone: #000000; color: var(--tone); transition: --tone 1s linear }
            p:hover { --tone: #ffffff }
            </style><p id="p">text</p>');
        $tui = new Tui(terminal: new VirtualTerminal(20, 4));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $color = fn () => (fn () => $this->renderer->resolveStyle($view->getById('p'))->getColor()?->toHex())->call($tui);

        $view->getById('p')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->now += 0.5;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $color(), 'Halfway, and what uses it follows.');
        $tui->stop();
    }

    private function build(string $css, string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
    }
}
