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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class FlexColumnAlignTest extends TestCase
{
    public function testAlignSelfPlacesAnItemAcross()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch } p { margin: 0; background: #333333 } .c { align-self: center } .e { align-self: flex-end } .s { align-self: start }',
            '<div class="col"><p id="stretch">stretched</p><p id="c" class="c">centered</p><p id="e" class="e">at the end</p><p id="s" class="s">start</p></div>');

        $this->assertSame([0, 40], $rects['stretch'], 'By default, across the whole column.');
        $this->assertSame([16, 8], $rects['c'], 'As wide as its content, in the middle.');
        $this->assertSame([30, 10], $rects['e']);
        $this->assertSame([0, 5], $rects['s']);
    }

    public function testAlignItemsAndAlignSelfOverIt()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 } .x { align-self: stretch }',
            '<div class="col"><p id="a">abcd</p><p id="b" class="x">stretched</p></div>');

        $this->assertSame([18, 4], $rects['a']);
        $this->assertSame([0, 40], $rects['b'], 'align-self wins.');
    }

    public function testAPercentageWidthIsOfTheColumnNotOfTheContent()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 } #full { width: 100% } #half { width: 50% }',
            '<div class="col"><p id="full">bar</p><p id="half">half</p></div>');

        $this->assertSame([0, 40], $rects['full'], 'Like the bar of the header of GitHub, its logo at one end and its buttons at the other.');
        $this->assertSame([10, 20], $rects['half']);

        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 }', '<div class="col"><p id="least" style="width: 10%; min-width: 20ch">x</p></div>');
        $this->assertSame([10, 20], $rects['least'], 'No narrower than its min-width.');
    }

    /**
     * @return array<string, array{int, int}> Column and width by id
     */
    private function layout(string $css, string $html): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(60, 10));
        $view = (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $rects = [];
        foreach ($view->querySelectorAll('p[id]') as $widget) {
            $rect = $tui->getWidgetRect($widget);
            $rects[$widget->getId()] = [$rect->col, $rect->columns];
        }

        return $rects;
    }
}
