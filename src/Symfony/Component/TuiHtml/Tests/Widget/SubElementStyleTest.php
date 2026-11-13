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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\StyleSheet;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\ProgressBarWidget;

final class SubElementStyleTest extends TestCase
{
    public function testTheTextAfterASubElementKeepsTheWidgetStyle()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(null, $terminal);
        $tui->addStyleSheet(new StyleSheet([SubElementWidget::class => new Style(null, null, null, 'red'), SubElementWidget::class.'::key' => new Style(null, null, null, 'blue')]));
        $tui->add(new SubElementWidget());
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(20, 3);
        $screen->write($terminal->getOutput());
        // "[K] rest": the key in blue, the rest of the line still in red, like before the key
        $line = explode("\n", $screen->getStyledScreen())[0];
        $this->assertStringContainsString("\x1b[34mK", $line);
        $this->assertStringContainsString("\x1b[31m] rest", $line);
    }

    public function testTheTextAfterASubElementKeepsTheStyleOfTheContainer()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(null, $terminal);
        $tui->addStyleSheet(new StyleSheet(['.box' => new Style(null, null, null, 'red'), SubElementWidget::class.'::key' => new Style(null, null, null, 'blue')]));
        $box = new ContainerWidget();
        $box->addStyleClass('box');
        $box->add(new SubElementWidget());
        $tui->add($box);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(20, 3);
        $screen->write($terminal->getOutput());
        $line = explode("\n", $screen->getStyledScreen())[0];
        $this->assertStringContainsString("\x1b[34mK", $line);
        $this->assertStringContainsString("\x1b[31m] rest", $line);
    }

    public function testTheTextAfterASubElementThatCancelsTheBackgroundKeepsTheBackgroundOfTheContainer()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(null, $terminal);
        $tui->addStyleSheet(new StyleSheet(['.box' => new Style(null, null, 'blue'), SubElementWidget::class.'::key' => new Style(null, null, 'default', 'yellow')]));
        $box = new ContainerWidget();
        $box->addStyleClass('box');
        $box->add(new SubElementWidget());
        $tui->add($box);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(20, 3);
        $screen->write($terminal->getOutput());
        $line = explode("\n", $screen->getStyledScreen())[0];
        $this->assertStringContainsString("\x1b[33mK", $line);
        $this->assertStringContainsString("\x1b[44m] rest", $line);
    }

    public function testTheTextAfterANestedSubElementKeepsTheStyleOfTheOuterOne()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(null, $terminal);
        $tui->addStyleSheet(new StyleSheet([ProgressBarWidget::class => new Style(null, null, null, 'red'), ProgressBarWidget::class.'::text' => new Style(null, null, null, 'yellow'), ProgressBarWidget::class.'::bar-fill' => new Style(null, null, null, 'green'), ProgressBarWidget::class.'::bar-empty' => new Style(null, null, null, 'blue')]));
        $progressBar = new ProgressBarWidget(10, '[%bar%] done');
        $progressBar->setBarWidth(4);
        $progressBar->setProgress(5);
        $tui->add($progressBar);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(20, 3);
        $screen->write($terminal->getOutput());
        $line = explode("\n", $screen->getStyledScreen())[0];
        $this->assertStringContainsString("\x1b[33m] done", $line);
    }
}
final class SubElementWidget extends AbstractWidget
{
    public function render(\Symfony\Component\Tui\Render\RenderContext $context): array
    {
        $context = ContextBridge::computed($context);

        return ['['.$this->applyElement('key', 'K').'] rest'];
    }
}
