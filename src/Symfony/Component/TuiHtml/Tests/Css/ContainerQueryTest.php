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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\ContainerQuery;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class ContainerQueryTest extends TestCase
{
    public function testParse()
    {
        $this->assertNull(ContainerQuery::parse('(min-width: 30ch)')->name);
        $this->assertSame('card', ContainerQuery::parse('card (width > 30)')->name);
        $this->assertNull(ContainerQuery::parse('not (width > 30)')->name);
    }

    public function testAConditionIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        ContainerQuery::parse('card');
    }

    public function testOneFrameIsEnoughAfterAResize()
    {
        $terminal = new VirtualTerminal(60, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build('<style>
            main { container-type: inline-size }
            .card { display: flex; flex-direction: column }
            @container (min-width: 40ch) { .card { flex-direction: row } }
        </style><main><div class="card" id="card"><p>a</p><p>b</p></div></main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $card = $view->getById('card');
        $this->assertNotNull($card);
        $style = static fn () => $view->getStyleSheet()->getStyles($card)[0] ?? null;
        $this->assertSame(Direction::Horizontal, $style()?->getDirection(), 'The first frame already applies the query.');
        $terminal->simulateResize(30, 10);
        $tui->processRender();
        $this->assertSame(Direction::Vertical, $style()?->getDirection());
    }

    public function testWithoutContainerTheQueryDoesNotMatch()
    {
        $terminal = new VirtualTerminal(60, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build('<style>@container (min-width: 1ch) { p { font-weight: bold } }</style><p id="p">a</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $p = $view->getById('p');
        $this->assertNotNull($p);
        $this->assertNotTrue(($view->getStyleSheet()->getStyles($p)[0] ?? null)?->getBold());
    }

    public function testScrollStateQueries()
    {
        $query = ContainerQuery::parse('header scroll-state(stuck: top)');
        $this->assertSame('header', $query->name);
        $this->assertTrue($query->matchesScrollState(['stuck' => ['top']]));
        $this->assertFalse($query->matchesScrollState(['stuck' => ['bottom']]));
        $this->assertFalse($query->matchesScrollState([]));
        $this->assertTrue(ContainerQuery::parse('scroll-state(stuck: none)')->matchesScrollState([]));
        $this->assertTrue(ContainerQuery::parse('scroll-state(scrollable: y)')->matchesScrollState(['scrollable' => ['bottom']]), 'y is top or bottom.');
        $this->assertTrue(ContainerQuery::parse('scroll-state((snapped: block))')->matchesScrollState(['snapped' => ['y']]));
        $this->assertFalse(ContainerQuery::parse('scroll-state(snapped: both)')->matchesScrollState(['snapped' => ['y']]));
        $this->assertTrue(ContainerQuery::parse('not scroll-state(scrolled: block-end)')->matchesScrollState(['scrolled' => ['top']]));
        $this->assertTrue(ContainerQuery::parse('scroll-state(stuck: top) or scroll-state(scrollable: top)')->matchesScrollState(['scrollable' => ['top']]));
        $this->assertFalse(ContainerQuery::parse('scroll-state(stuck: top) and scroll-state(scrollable: top)')->matchesScrollState(['scrollable' => ['top']]));
    }

    public function testInvalidScrollStateQuery()
    {
        $this->expectException(InvalidArgumentException::class);
        ContainerQuery::parse('scroll-state(stuck: sideways)');
    }
}
