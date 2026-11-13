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
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;

final class CssWideKeywordsTest extends TestCase
{
    public function testUnsetInheritsAnInheritedProperty()
    {
        $view = $this->build('div { color: #ff0000; padding: 0 2ch } p { color: #00ff00; padding: 1 } p.unset { color: unset; padding: unset }', '<div><p id="p" class="unset">text</p></div>');
        $sheet = $view->getStyleSheet();

        $this->assertSame('#ff0000', $sheet->getComputedValue($view->getById('p'), 'color'), 'unset on an inherited property: the parent value.');
        $this->assertSame('initial', $sheet->getComputedValue($view->getById('p'), 'padding'), 'unset on another one: its initial value.');
    }

    public function testRevertGoesBackToTheUserAgentValue()
    {
        $view = $this->build('summary, button { display: none } button { display: revert }', '<button id="b">b</button>');

        $this->assertNotSame('none', $view->getStyleSheet()->getComputedValue($view->getById('b'), 'display'));
        $this->assertFalse((bool) $view->getById('b')->getStyle()?->isHidden());
    }

    public function testAll()
    {
        $view = $this->build(
            'section { color: #ff0000 } h2 { color: #00ff00; border: 1px solid; --tone: blue; text-align: center }
             h2.reset { all: unset } h2.initial { all: initial } h2.revert { all: revert; color: #0000ff }',
            '<section><h2 id="unset" class="reset">a</h2><h2 id="initial" class="initial">b</h2><h2 id="revert" class="revert">c</h2></section>',
        );
        $sheet = $view->getStyleSheet();
        $value = static fn (string $id, string $property) => $sheet->getComputedValue($view->getById($id), $property);

        $this->assertSame('#ff0000', $value('unset', 'color'), 'all: unset inherits what inherits.');
        $this->assertSame('initial', $value('unset', 'border'), 'And resets the others.');
        $this->assertSame('blue', $value('unset', '--tone'), 'Not the custom properties.');
        $this->assertSame('initial', $value('initial', 'color'), 'all: initial does not inherit.');
        $this->assertSame('#0000ff', $value('revert', 'color'), 'What follows all wins.');
        $this->assertNotSame('center', $value('revert', 'text-align'));
    }

    private function build(string $css, string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
    }
}
