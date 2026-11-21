<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Widget\MarqueeWidget;
use Symfony\Component\TuiHtml\Widget\Text\InlineNode;

final class MarqueeWidgetTest extends TestCase
{
    public function testScroll()
    {
        $this->assertSame(['      ', '     H', '    Hi', '   Hi ', '  Hi  ', ' Hi   ', 'Hi    ', 'i     ', '      ', '     H'], $this->frames(new MarqueeWidget([InlineNode::text('Hi')]), 10), 'In from the right, out at the left, again.');
        $this->assertSame(['      ', 'i     ', 'Hi    ', ' Hi   '], $this->frames(new MarqueeWidget([InlineNode::text('Hi')], 'right'), 4), 'From the left.');
        $this->assertSame(['      ', '    Hi', '  Hi  ', 'Hi    '], $this->frames(new MarqueeWidget([InlineNode::text('Hi')], 'left', 'scroll', 2), 4), 'Two columns a tick.');
    }

    public function testSlideAndAlternate()
    {
        $this->assertSame(['      ', '     H', '    Hi', '   Hi ', '  Hi  ', ' Hi   ', 'Hi    ', 'Hi    ', 'Hi    '], $this->frames(new MarqueeWidget([InlineNode::text('Hi')], 'left', 'slide'), 9), 'In, then still.');
        $this->assertSame(['    Hi', '   Hi ', '  Hi  ', ' Hi   ', 'Hi    ', ' Hi   ', '  Hi  '], $this->frames(new MarqueeWidget([InlineNode::text('Hi')], 'left', 'alternate'), 7), 'From one side to the other and back.');
    }

    public function testLoop()
    {
        $frames = $this->frames(new MarqueeWidget([InlineNode::text('Hi')], 'left', 'scroll', 1, 0.08500000000000001, 1), 12);
        $this->assertSame('Hi    ', $frames[11], 'Its loop done, at its start.');
    }

    public function testMarqueeElement()
    {
        $view = (new HtmlBuilder())->build('<marquee id="m" behavior="alternate" direction="right" scrollamount="16" scrolldelay="10">News</marquee><marquee id="block"><p>Block</p></marquee>');
        $marquee = $view->getById('m');
        $this->assertInstanceOf(MarqueeWidget::class, $marquee);
        $plain = static fn (array $lines) => array_map(static fn (string $line) => preg_replace('/\e\[[0-9;]*m/', '', $line), $lines);
        $this->assertSame(['News  '], $plain($marquee->render(new RenderContext(6, 1))));
        $marquee->tick();
        $this->assertSame(['  News'], $plain($marquee->render(new RenderContext(6, 1))), 'Two columns a tick (16 CSS pixels), to the right.');
        $this->assertNotInstanceOf(MarqueeWidget::class, $view->getById('block'), 'Holding blocks: a block.');
    }

    private function frames(MarqueeWidget $marquee, int $count): array
    {
        $frames = [];
        for ($i = 0; $i < $count; ++$i) {
            $frames[] = preg_replace('/\e\[[0-9;]*m/', '', $marquee->render(new RenderContext(6, 1))[0]);
            $marquee->tick();
        }

        return $frames;
    }
}
