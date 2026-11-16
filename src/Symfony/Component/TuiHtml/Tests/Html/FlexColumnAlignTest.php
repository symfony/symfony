<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class FlexColumnAlignTest extends TestCase
{
    public function testAlignSelfPlacesAnItemAcross()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch } p { margin: 0; background: #333333 } .c { align-self: center } .e { align-self: flex-end } .s { align-self: start }', '<div class="col"><p id="stretch">stretched</p><p id="c" class="c">centered</p><p id="e" class="e">at the end</p><p id="s" class="s">start</p></div>');
        $this->assertSame([0, 40], $rects['stretch'], 'By default, across the whole column.');
        $this->assertSame([16, 8], $rects['c'], 'As wide as its content, in the middle.');
        $this->assertSame([30, 10], $rects['e']);
        $this->assertSame([0, 5], $rects['s']);
    }

    public function testAlignItemsAndAlignSelfOverIt()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 } .x { align-self: stretch }', '<div class="col"><p id="a">abcd</p><p id="b" class="x">stretched</p></div>');
        $this->assertSame([18, 4], $rects['a']);
        $this->assertSame([0, 40], $rects['b'], 'align-self wins.');
    }

    public function testAPercentageWidthIsOfTheColumnNotOfTheContent()
    {
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 } #full { width: 100% } #half { width: 50% }', '<div class="col"><p id="full">bar</p><p id="half">half</p></div>');
        $this->assertSame([0, 40], $rects['full'], 'Like the bar of the header of GitHub, its logo at one end and its buttons at the other.');
        $this->assertSame([10, 20], $rects['half']);
        $rects = $this->layout('.col { display: flex; flex-direction: column; width: 40ch; align-items: center } p { margin: 0 }', '<div class="col"><p id="least" style="width: 10%; min-width: 20ch">x</p></div>');
        $this->assertSame([10, 20], $rects['least'], 'No narrower than its min-width.');
    }

    private function layout(string $css, string $html): array
    {
        $tui = new Tui(null, new VirtualTerminal(60, 10));
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

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideStartAlignedSizes(): iterable
    {
        yield 'a min-width over a width' => ['width: 4ch; min-width: 8ch', 8];
        yield 'a min-width of a share of the column' => ['width: 4ch; min-width: 40%', 8];
        yield 'a max-width under a share' => ['width: 50%; max-width: 6ch', 6];
        yield 'a min-width over the content' => ['min-width: 8ch', 8];
        yield 'a max-width over the content' => ['max-width: 6ch', 1];
    }

    #[DataProvider('provideStartAlignedSizes')]
    public function testAStartAlignedItemKeepsItsMinAndMaxWidth(string $css, int $expected)
    {
        // Like CSS: a width set within its min-width and max-width, like a block; or its content within them
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="width: 20ch; display: flex; flex-direction: column; align-items: start"><div style="background: #ff0000; '.$css.'">x</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(40, 5);
        $screen->write($terminal->getOutput());
        $this->assertSame(1, preg_match('/\e\[48;2;255;0;0m([^\e]*)/', explode("\n", $screen->getStyledScreen())[0], $match));
        $this->assertSame($expected, mb_strlen($match[1]));
    }
}
