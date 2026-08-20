<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RenderRequestorInterface;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Transition\SlideTransition;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\TransitionWidget;
use Symfony\Component\Tui\Widget\WidgetTree;

class TransitionWidgetTest extends TestCase
{
    public function testGetTo()
    {
        $widget = new TransitionWidget();
        $to = new TextWidget('To');

        $widget->start(new TextWidget('From'), $to, new SlideTransition());

        $this->assertSame($to, $widget->getTo());
    }

    public function testAllReturnsBothWidgets()
    {
        $widget = new TransitionWidget();
        $from = new TextWidget('From');
        $to = new TextWidget('To');

        $widget->start($from, $to, new SlideTransition());

        $this->assertCount(2, $widget->all());
        $this->assertContains($from, $widget->all());
        $this->assertContains($to, $widget->all());
    }

    public function testStartActivatesTheTransitionAtProgressZero()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 1.0);

        $this->assertTrue($widget->isActive());
        $this->assertSame(0.0, $widget->getProgress());
    }

    #[DataProvider('nonPositiveDurationProvider')]
    public function testStartRejectsNonPositiveDurations(float $duration)
    {
        $widget = new TransitionWidget();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be greater than 0');

        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), $duration);
    }

    public static function nonPositiveDurationProvider(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'negative fraction' => [-0.001];
    }

    public function testTickAdvancesProgressByTheGivenDelta()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 2.0);

        $widget->tick(0.5);

        $this->assertEqualsWithDelta(0.25, $widget->getProgress(), 1e-9);

        $widget->tick(0.5);

        $this->assertEqualsWithDelta(0.5, $widget->getProgress(), 1e-9);
    }

    public function testTickWithoutDeltaFollowsWallClock()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 10.0);

        usleep(20_000);
        $widget->tick();

        // 20ms out of a 10s transition: a frame-counting tick would report a fixed 0.0016
        // whatever the elapsed time, so only a clock-reading tick lands in this window.
        $this->assertGreaterThan(0.0, $widget->getProgress());
        $this->assertLessThan(0.05, $widget->getProgress());
    }

    public function testProgressClampsAtOneAndDeactivates()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 1.0);

        $widget->tick(0.6);
        $this->assertTrue($widget->isActive());

        $widget->tick(0.6);
        $this->assertSame(1.0, $widget->getProgress());
        $this->assertFalse($widget->isActive());

        // Further ticks are no-ops and never overshoot.
        $this->assertFalse($widget->tick(0.6));
        $this->assertSame(1.0, $widget->getProgress());
    }

    public function testTickReturnsFalseWhenNotActive()
    {
        $widget = new TransitionWidget();

        $this->assertFalse($widget->tick());
    }

    public function testTickReturnsTrueWhenActive()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 1.0);

        $this->assertTrue($widget->tick(0.1));
    }

    public function testRestartResetsProgress()
    {
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 1.0);
        $widget->tick(0.5);

        $widget->start(new TextWidget('From2'), new TextWidget('To2'), new SlideTransition(), 1.0);

        $this->assertSame(0.0, $widget->getProgress());
        $this->assertTrue($widget->isActive());
    }

    public function testRenderReturnsEmptyWithoutContext()
    {
        $widget = new TransitionWidget();

        $this->assertSame([], $widget->render(new RenderContext(80, 24)));
    }

    public function testStartBeforeAttachIsResumedOnceAttached()
    {
        $tui = new Tui(terminal: new VirtualTerminal(20, 5));

        // start() runs while getContext() is still null: the interval is recorded, and
        // onAttach() is what actually hands it to the scheduler.
        $widget = new TransitionWidget();
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 10.0);

        $tui->add($widget);
        $tui->start();
        usleep(20_000);
        $tui->tick();
        $tui->stop();

        $this->assertGreaterThan(0.0, $widget->getProgress());
    }

    public function testScheduledTickStopsOnDetach()
    {
        $tui = new Tui(terminal: new VirtualTerminal(20, 5));

        $widget = new TransitionWidget();
        $tui->add($widget);
        $widget->start(new TextWidget('From'), new TextWidget('To'), new SlideTransition(), 10.0);

        $tui->start();
        usleep(20_000);
        $tui->tick();

        $progressWhileAttached = $widget->getProgress();
        $this->assertGreaterThan(0.0, $progressWhileAttached);

        $tui->remove($widget);
        usleep(20_000);
        $tui->tick();
        $tui->stop();

        $this->assertSame($progressWhileAttached, $widget->getProgress());
    }

    public function testRenderBlendsBothScreensMidTransition()
    {
        [$root, $widget] = $this->buildTree(new TextWidget('AAAAAAAA'), new TextWidget('BBBBBBBB'));
        $widget->tick(0.016);

        $lines = (new Renderer())->renderFrame($root, 12, 4)->toArray();
        $content = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringContainsString('A', $content);
        $this->assertStringContainsString('B', $content);
        foreach ($lines as $line) {
            $this->assertSame(12, AnsiUtils::visibleWidth($line));
        }
    }

    public function testFullWidthScreensArePassedThroughUnpadded()
    {
        [$root, $widget] = $this->buildTree(new TextWidget('AAAAAAAA'), new TextWidget('BBBBBBBB'));
        $widget->tick(0.016);

        $lines = (new Renderer())->renderFrame($root, 8, 4)->toArray();
        $content = AnsiUtils::stripAnsiCodes(implode("\n", $lines));

        $this->assertStringContainsString('A', $content);
        $this->assertStringContainsString('B', $content);
        foreach ($lines as $line) {
            $this->assertSame(8, AnsiUtils::visibleWidth($line));
        }
    }

    public function testRenderShowsDestinationWhenFinished()
    {
        [$root, $widget] = $this->buildTree(new TextWidget('AAAAAAAA'), new TextWidget('BBBBBBBB'));
        $widget->tick(0.016);
        $widget->tick(0.016);

        $content = AnsiUtils::stripAnsiCodes(implode("\n", (new Renderer())->renderFrame($root, 12, 4)->toArray()));

        $this->assertStringContainsString('B', $content);
        $this->assertStringNotContainsString('A', $content);
    }

    public function testRestartWhileAttachedSwapsChildren()
    {
        [$root, $widget] = $this->buildTree(new TextWidget('AAAAAAAA'), new TextWidget('BBBBBBBB'));
        $newTo = new TextWidget('DDDDDDDD');
        $widget->start(new TextWidget('CCCCCCCC'), $newTo, new SlideTransition(), 0.032);

        $this->assertSame($newTo, $widget->getTo());

        $widget->tick(0.016);
        $content = AnsiUtils::stripAnsiCodes(implode("\n", (new Renderer())->renderFrame($root, 12, 4)->toArray()));

        $this->assertStringContainsString('C', $content);
        $this->assertStringContainsString('D', $content);
        $this->assertStringNotContainsString('A', $content);
    }

    public function testRenderedLinesNeverEndOnAnOpenStyle()
    {
        [, $widget] = $this->buildTree(
            new TextWidget('AAAAAAAA')->addStyleClass('from-screen'),
            new TextWidget('BBBBBBBB')->addStyleClass('to-screen'),
            new StyleSheet([
                '.from-screen' => new Style(background: 'red'),
                '.to-screen' => new Style(background: 'blue'),
            ]),
        );
        $widget->tick(0.016);

        $styled = 0;
        foreach ($widget->render(new RenderContext(12, 4)) as $line) {
            if (!str_contains($line, "\x1b")) {
                continue;
            }

            ++$styled;
            $this->assertStringEndsWith("\x1b[0m", $line);
        }

        $this->assertGreaterThan(0, $styled, 'The blend is expected to emit styled lines.');
    }

    /**
     * @return array{ContainerWidget, TransitionWidget}
     */
    private function buildTree(TextWidget $from, TextWidget $to, ?StyleSheet $styleSheet = null): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $tree = new WidgetTree(
            $tui,
            new Keybindings(),
            new FocusManager($this->createStub(RenderRequestorInterface::class)),
            new Renderer($styleSheet),
            $terminal,
            $tui->getEventDispatcher(),
        );

        $widget = new TransitionWidget();
        $widget->expandVertically(true);
        $widget->start($from, $to, new SlideTransition(), 0.032);

        $root = new ContainerWidget();
        $root->expandVertically(true);
        $root->add($widget);
        $tree->setRoot($root);

        return [$root, $widget];
    }
}
