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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\LoaderWidget;

class LoaderTest extends TestCase
{
    public function testRenderIncludesBlankLine()
    {
        $loader = new LoaderWidget(message: 'Test');

        $lines = $loader->render(new RenderContext(80, 24));

        // First line should be blank, second line has the spinner + message
        $this->assertSame('', $lines[0]);
        $this->assertCount(2, $lines);
    }

    public function testRenderIncludesSpinnerAndMessage()
    {
        $loader = new LoaderWidget(message: 'Working...');

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        // Should contain the message
        $this->assertStringContainsString('Working...', $content);
        // Should contain a spinner character (one of the braille frames)
        $this->assertMatchesRegularExpression('/[⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]/', $content, 'Render output should contain a spinner frame');
    }

    public function testSubElementStylesFromStylesheet()
    {
        // Verify that LoaderWidget resolves ::spinner and ::message sub-elements
        // from the stylesheet when rendered with a Tui context
        $terminal = new VirtualTerminal(80, 24);
        $loader = new LoaderWidget(message: 'Test');

        $stylesheet = new StyleSheet([
            LoaderWidget::class.'::spinner' => new Style()->withBold(),
            LoaderWidget::class.'::message' => new Style()->withUnderline(),
        ]);

        $tui = new Tui($stylesheet, $terminal);
        $tui->add($loader);

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        $this->assertStringContainsString("\x1b[1m", $content);
        $this->assertStringContainsString("\x1b[4m", $content);
        $this->assertStringContainsString('Test', $content);
    }

    public function testSetSpinnerStyle()
    {
        $loader = new LoaderWidget(message: 'Test');
        $loader->setSpinner('line');

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        $this->assertStringContainsString('-', $content);
        $this->assertStringContainsString('Test', $content);
    }

    public function testUnknownSpinnerStyleThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown loader style "nope".');

        $loader = new LoaderWidget();
        $loader->setSpinner('nope');
    }

    public function testAddSpinnerStyle()
    {
        LoaderWidget::addSpinner('custom', ['A', 'B', 'C']);

        $loader = new LoaderWidget(message: 'Test');
        $loader->setSpinner('custom');

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        $this->assertStringContainsString('A', $content);
        $this->assertStringContainsString('Test', $content);
    }

    public function testAddSpinnerStyleRequiresAtLeastTwo()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have at least 2 indicator frame characters.');

        LoaderWidget::addSpinner('bad', ['X']);
    }

    public function testFinishedIndicatorShownAfterStop()
    {
        $loader = new LoaderWidget(message: 'Done!');
        $loader->setFinishedIndicator('✔');
        $loader->stop();

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        $this->assertStringContainsString('✔', $content);
        $this->assertStringContainsString('Done!', $content);
    }

    public function testDefaultFinishedIndicatorRendersEmpty()
    {
        $loader = new LoaderWidget(message: 'Test');
        $loader->stop();

        $lines = $loader->render(new RenderContext(80, 24));

        $this->assertSame([], $lines);
    }

    public function testStartResetsFinishedState()
    {
        $loader = new LoaderWidget(message: 'Test');
        $loader->setFinishedIndicator('✔');
        $loader->stop();
        $loader->start();

        $lines = $loader->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        // Should show spinner frame, not the finished indicator
        $this->assertMatchesRegularExpression('/[⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏]/', $content);
        $this->assertStringNotContainsString('✔', $content);
    }

    public function testTickAdvancesWithElapsedDelta()
    {
        $loader = new LoaderWidget(message: 'Test');
        $loader->setSpinner('line');
        $loader->setIntervalMs(80);

        $loader->tick(0.24);
        $this->assertSame('/', $loader->getSpinnerFrame());
    }

    public function testSetIntervalMustBePositive()
    {
        $loader = new LoaderWidget();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval must be greater than 0');
        $loader->setIntervalMs(0);
    }

    public function testAttachSchedulesTickWhenRunning()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $loader = new LoaderWidget();
        $tui->add($loader);

        $this->assertNotNull($this->getScheduledTickId($loader));
    }

    public function testDetachClearsScheduledTickId()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $loader = new LoaderWidget();
        $tui->add($loader);
        $this->assertNotNull($this->getScheduledTickId($loader));

        $tui->remove($loader);
        $this->assertNull($this->getScheduledTickId($loader));
    }

    public function testSetIntervalReschedulesTickWhenRunningAndAttached()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $loader = new LoaderWidget();
        $tui->add($loader);

        $firstId = $this->getScheduledTickId($loader);
        $this->assertNotNull($firstId);

        $loader->setIntervalMs(120);

        $secondId = $this->getScheduledTickId($loader);
        $this->assertNotNull($secondId);
        $this->assertNotSame($firstId, $secondId);
    }

    private function getScheduledTickId(LoaderWidget $loader): ?string
    {
        $property = new \ReflectionProperty($loader, 'scheduledTickId');

        return $property->getValue($loader);
    }
}
