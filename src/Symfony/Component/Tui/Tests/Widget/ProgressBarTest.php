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
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ProgressBarWidget;

/**
 * @covers \Symfony\Component\Tui\Widget\ProgressBarWidget
 */
class ProgressBarTest extends TestCase
{
    public function testConstructionDeterminate()
    {
        $bar = new ProgressBarWidget(100);

        $this->assertSame(100, $bar->getMaxSteps());
        $this->assertSame(0, $bar->getProgress());
        $this->assertSame(0.0, $bar->getProgressPercent());
    }

    public function testConstructionIndeterminate()
    {
        $bar = new ProgressBarWidget();

        $this->assertSame(0, $bar->getMaxSteps());
        $this->assertSame(0, $bar->getProgress());
    }

    public function testAdvance()
    {
        $bar = new ProgressBarWidget(100);
        $bar->advance(10);

        $this->assertSame(10, $bar->getProgress());
        $this->assertEqualsWithDelta(0.1, $bar->getProgressPercent(), 0.001);

        $bar->advance(5);
        $this->assertSame(15, $bar->getProgress());
    }

    public function testSetProgress()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setProgress(50);

        $this->assertSame(50, $bar->getProgress());
        $this->assertEqualsWithDelta(0.5, $bar->getProgressPercent(), 0.001);
    }

    public function testSetProgressBeyondMaxExtendsMax()
    {
        $bar = new ProgressBarWidget(50);
        $bar->setProgress(75);

        $this->assertSame(75, $bar->getMaxSteps());
        $this->assertSame(75, $bar->getProgress());
    }

    public function testSetProgressNegativeClampsToZero()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setProgress(50);
        $bar->setProgress(-10);

        $this->assertSame(0, $bar->getProgress());
    }

    public function testFinish()
    {
        $bar = new ProgressBarWidget(100);
        $bar->advance(30);
        $bar->finish();

        $this->assertSame(100, $bar->getProgress());
        $this->assertEqualsWithDelta(1.0, $bar->getProgressPercent(), 0.001);
        $this->assertFalse($bar->isRunning());
    }

    public function testFinishIndeterminate()
    {
        $bar = new ProgressBarWidget();
        $bar->advance(42);
        $bar->finish();

        $this->assertSame(42, $bar->getMaxSteps());
        $this->assertSame(42, $bar->getProgress());
        $this->assertFalse($bar->isRunning());
    }

    public function testStartResetsState()
    {
        $bar = new ProgressBarWidget(100);
        $bar->advance(50);
        $bar->start(200, 10);

        $this->assertSame(200, $bar->getMaxSteps());
        $this->assertSame(10, $bar->getProgress());
        $this->assertTrue($bar->isRunning());
    }

    public function testStartKeepsMaxIfNull()
    {
        $bar = new ProgressBarWidget(100);
        $bar->start();

        $this->assertSame(100, $bar->getMaxSteps());
        $this->assertSame(0, $bar->getProgress());
    }

    public function testBarWidth()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(40);

        $this->assertSame(40, $bar->getBarWidth());
    }

    public function testBarWidthMinimumIsOne()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(0);

        $this->assertSame(1, $bar->getBarWidth());
    }

    public function testBarCharacters()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarCharacter('=');
        $bar->setEmptyBarCharacter('-');
        $bar->setProgressCharacter('>');

        $this->assertSame('=', $bar->getBarCharacter());
        $this->assertSame('-', $bar->getEmptyBarCharacter());
        $this->assertSame('>', $bar->getProgressCharacter());
    }

    public function testFormat()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('Progress: %percent%%');

        $this->assertSame('Progress: %percent%%', $bar->getFormat());
    }

    public function testMessages()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setMessage('Downloading files...');

        $this->assertSame('Downloading files...', $bar->getMessage());
        $this->assertNull($bar->getMessage('other'));
    }

    public function testNamedMessages()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setMessage('file.txt', 'filename');

        $this->assertSame('file.txt', $bar->getMessage('filename'));
    }

    public function testRenderDeterminate()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(10);
        $bar->setBarCharacter('=');
        $bar->setEmptyBarCharacter('-');
        $bar->setProgressCharacter('>');

        $bar->setProgress(50);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('50/100', $content);
        $this->assertStringContainsString('50%', $content);
        $this->assertStringContainsString('=====', $content);
        $this->assertStringContainsString('>', $content);
        $this->assertStringContainsString('----', $content);
    }

    public function testRenderComplete()
    {
        $bar = new ProgressBarWidget(10);
        $bar->setBarWidth(10);
        $bar->setBarCharacter('=');
        $bar->setEmptyBarCharacter('-');
        $bar->setProgressCharacter('>');

        $bar->setProgress(10);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        // At 100%, no empty bar characters
        $this->assertStringContainsString('100%', $content);
        $this->assertStringContainsString('==========', $content);
        $this->assertStringNotContainsString('-', $content);
    }

    public function testRenderIndeterminate()
    {
        $bar = new ProgressBarWidget();
        $bar->setBarWidth(10);

        $bar->advance(3);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        // Should show current count, no percentage or max
        $this->assertStringContainsString('3', $content);
        $this->assertStringNotContainsString('%', $content);
    }

    public function testRenderWithMessagePlaceholder()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%message% %percent%%');
        $bar->setMessage('Installing...');

        $bar->setProgress(25);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('Installing...', $content);
        $this->assertStringContainsString('25%', $content);
    }

    public function testCustomPlaceholderFormatter()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%custom%');
        $bar->setPlaceholderFormatter('custom', static fn (ProgressBarWidget $b) => 'step-'.$b->getProgress());

        $bar->setProgress(7);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('step-7', $content);
    }

    public function testDefaultPlaceholderFormatter()
    {
        ProgressBarWidget::setDefaultPlaceholderFormatter('global_test', static fn (ProgressBarWidget $b) => 'G'.$b->getProgress());

        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%global_test%');
        $bar->setProgress(5);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('G5', $content);
    }

    public function testInstanceFormatterOverridesDefault()
    {
        ProgressBarWidget::setDefaultPlaceholderFormatter('override_test', static fn (ProgressBarWidget $b) => 'DEFAULT');

        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%override_test%');
        $bar->setPlaceholderFormatter('override_test', static fn (ProgressBarWidget $b) => 'INSTANCE');

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('INSTANCE', $content);
        $this->assertStringNotContainsString('DEFAULT', $content);
    }

    public function testUnknownPlaceholderLeftAsIs()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%nonexistent%');

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        $this->assertStringContainsString('%nonexistent%', $content);
    }

    public function testSetMaxStepsToZeroMakesIndeterminate()
    {
        $bar = new ProgressBarWidget(100);
        $this->assertSame(100, $bar->getMaxSteps());

        $bar->setMaxSteps(0);
        $this->assertSame(0, $bar->getMaxSteps());
    }

    public function testStepWidth()
    {
        $bar = new ProgressBarWidget(1000);

        $this->assertSame(4, $bar->getStepWidth());
    }

    public function testStepWidthIndeterminate()
    {
        $bar = new ProgressBarWidget();

        $this->assertSame(4, $bar->getStepWidth());
    }

    public function testBarOffset()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(20);
        $bar->setProgress(50);

        $this->assertSame(10, $bar->getBarOffset());
    }

    public function testBarOffsetIndeterminate()
    {
        $bar = new ProgressBarWidget();
        $bar->setBarWidth(10);
        $bar->advance(13);

        // 13 % 10 = 3
        $this->assertSame(3, $bar->getBarOffset());
    }

    public function testFormatSpecifiers()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%percent:3s%%');
        $bar->setProgress(5);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        // %3s should right-pad "5" to "  5"
        $this->assertStringContainsString('  5%', $content);
    }

    public function testLineFillsAvailableWidth()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setProgress(50);

        $lines = $bar->render(new RenderContext(60, 24));
        $content = $lines[0];

        // The rendered line should be padded to 60 columns
        $visibleWidth = mb_strwidth(preg_replace('/\x1b\[[^m]*m/', '', $content));
        $this->assertSame(60, $visibleWidth);
    }

    public function testTickWhenNotRunningReturnsFalse()
    {
        $bar = new ProgressBarWidget(100);
        $bar->finish();

        $this->assertFalse($bar->tick());
    }

    public function testTickWhenRunningReturnsTrue()
    {
        $bar = new ProgressBarWidget(100);
        $bar->start();

        $this->assertTrue($bar->tick());
    }

    public function testSubElementStylesFromStylesheet()
    {
        $terminal = new VirtualTerminal(80, 24);
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(10);
        $bar->setProgress(50);

        $stylesheet = new StyleSheet([
            ProgressBarWidget::class.'::bar-fill' => new Style()->withBold(),
            ProgressBarWidget::class.'::bar-empty' => new Style()->withDim(),
        ]);

        $tui = new Tui($stylesheet, $terminal);
        $tui->add($bar);

        $lines = $bar->render(new RenderContext(80, 24));
        $content = implode('', $lines);

        // Bold for fill, dim for empty
        $this->assertStringContainsString("\x1b[1m", $content);
        $this->assertStringContainsString("\x1b[2m", $content);
    }

    public function testRenderBarShrinkingToFitWidth()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setBarWidth(50);
        $bar->setBarCharacter('=');
        $bar->setEmptyBarCharacter('-');
        $bar->setProgressCharacter('');
        $bar->setProgress(50);

        // Render in a very narrow width
        $lines = $bar->render(new RenderContext(40, 24));
        $content = $lines[0];

        // Should not exceed 40 columns
        $visibleWidth = mb_strwidth(preg_replace('/\x1b\[[^m]*m/', '', $content));
        $this->assertLessThanOrEqual(40, $visibleWidth);
    }

    public function testMemoryPlaceholder()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%memory%');

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        // Should contain a memory measurement unit
        $this->assertTrue(
            str_contains($content, 'MiB')
            || str_contains($content, 'GiB')
            || str_contains($content, 'KiB')
            || str_contains($content, 'B'),
            'Memory placeholder should show a unit'
        );
    }

    public function testElapsedPlaceholder()
    {
        $bar = new ProgressBarWidget(100);
        $bar->setFormat('%elapsed%');
        $bar->start();

        $lines = $bar->render(new RenderContext(80, 24));
        $content = $lines[0];

        // Should contain time like "0:00"
        $this->assertMatchesRegularExpression('/\d+:\d{2}/', $content);
    }

    public function testPercentAtZeroMax()
    {
        $bar = new ProgressBarWidget(0);
        // Zero max → percent is 0 (indeterminate)
        $this->assertSame(0.0, $bar->getProgressPercent());
    }

    public function testEstimatedAndRemainingAtZeroStep()
    {
        $bar = new ProgressBarWidget(100);

        $this->assertSame(0.0, $bar->getEstimated());
        $this->assertSame(0.0, $bar->getRemaining());
    }

    public function testGetRemainingIndeterminate()
    {
        $bar = new ProgressBarWidget();
        $bar->advance(10);

        $this->assertSame(0.0, $bar->getRemaining());
    }

    public function testFormatConstants()
    {
        // Verify format constants contain expected placeholders
        $this->assertStringContainsString('%bar%', ProgressBarWidget::FORMAT_NORMAL);
        $this->assertStringContainsString('%percent:', ProgressBarWidget::FORMAT_NORMAL);
        $this->assertStringContainsString('%current%', ProgressBarWidget::FORMAT_NORMAL);
        $this->assertStringContainsString('%max%', ProgressBarWidget::FORMAT_NORMAL);

        $this->assertStringContainsString('%bar%', ProgressBarWidget::FORMAT_INDETERMINATE);
        $this->assertStringNotContainsString('%percent%', ProgressBarWidget::FORMAT_INDETERMINATE);
        $this->assertStringNotContainsString('%max%', ProgressBarWidget::FORMAT_INDETERMINATE);

        $this->assertStringContainsString('%elapsed:', ProgressBarWidget::FORMAT_VERBOSE);
        $this->assertStringContainsString('%estimated:', ProgressBarWidget::FORMAT_VERY_VERBOSE);
        $this->assertStringContainsString('%memory:', ProgressBarWidget::FORMAT_DEBUG);
    }

    public function testAttachResumesScheduledTickWhenRunning()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $bar = new ProgressBarWidget(100);
        $bar->start();
        $tui->add($bar);

        $this->assertNotNull($this->getScheduledTickId($bar));
    }

    public function testDetachClearsScheduledTickId()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $bar = new ProgressBarWidget(100);
        $bar->start();
        $tui->add($bar);
        $this->assertNotNull($this->getScheduledTickId($bar));

        $tui->remove($bar);
        $this->assertNull($this->getScheduledTickId($bar));
    }

    private function getScheduledTickId(ProgressBarWidget $bar): ?string
    {
        $property = new \ReflectionProperty($bar, 'scheduledTickId');

        return $property->getValue($bar);
    }
}
