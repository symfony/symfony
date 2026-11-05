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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;

final class SubElementStyleTest extends TestCase
{
    public function testTheTextAfterASubElementKeepsTheWidgetStyle()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleSheet(new StyleSheet([
            SubElementWidget::class => new Style(color: 'red'),
            SubElementWidget::class.'::key' => new Style(color: 'blue'),
        ]));
        $tui->add(new SubElementWidget());
        $tui->start();
        $tui->processRender();

        $screen = new ScreenBuffer(20, 3);
        $screen->write($terminal->getOutput());

        // "[K] rest": the key in blue, the rest of the line still in red, like before the key
        $line = explode("\n", $screen->getStyledScreen())[0];
        $this->assertStringContainsString("\e[34mK", $line);
        $this->assertStringContainsString("\e[31m] rest", $line);
    }
}

final class SubElementWidget extends AbstractWidget
{
    public function render(RenderContext $context): array
    {
        return ['['.$this->applyElement('key', 'K').'] rest'];
    }
}
