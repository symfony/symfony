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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TabItem;
use Symfony\Component\Tui\Widget\TabPosition;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class TabsContentRendererTest extends TestCase
{
    public function testContentCallbackRetainsNativeChromeAndReportsItsLocalViewport(): void
    {
        foreach (TabPosition::cases() as $position) {
            $body = new TextWidget('Body');
            $tabs = new TabsWidget([new TabItem('first', 'First', $body)], $position);
            $tui = new Tui(null, new VirtualTerminal(20, 10));
            $tui->add($tabs);
            $tui->start();
            try {
                $calls = [];
                $context = new RenderContext(20, 10);
                $expected = $tabs->render($context);
                $actual = $tabs->renderWithContent($context, static function ($content, $inner, $row, $col) use (&$calls, $body): array {
                    self::assertSame($body, $content);
                    $calls[] = [$inner->getColumns(), $inner->getRows(), $row, $col];

                    return ['Body'];
                });
                self::assertSame($expected, $actual);
                $viewport = match ($position) {
                    TabPosition::Top => [20, 7, 3, 0],
                    TabPosition::Bottom => [20, 7, 0, 0],
                    TabPosition::Left => [12, 8, 1, 8],
                    TabPosition::Right => [12, 8, 1, 0],
                };
                self::assertSame([$viewport], $calls);
                try {
                    $tabs->renderWithContent($context, static fn () => throw new \RuntimeException('Stop'));
                } catch (\RuntimeException) {
                }
                self::assertSame($expected, $tabs->render($context));
            } finally {
                $tui->stop();
            }
        }
    }
}
