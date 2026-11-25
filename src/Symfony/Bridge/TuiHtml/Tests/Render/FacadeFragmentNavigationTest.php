<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeFragmentNavigationTest extends TestCase
{
    public function testPhysicalClickNavigatesTheActualNativeViewFragment(): void
    {
        foreach (['<p id="link" style="margin:0"><a href="#target">Jump</a></p><p id="target">Target</p>', '<tui-tabs><section title="First"><p id="link" style="margin:0"><a href="#target">Jump</a></p><p id="target">Target</p></section></tui-tabs>'] as $html) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
            $renderer = new FacadeRenderer(new Renderer());
            $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, $renderer);
            $view->mount($tui);
            $tui->start();
            try {
                $tui->processRender();
                $rect = $tui->getWidgetRect($view->getById('link'));
                self::assertSame('#target', $renderer->getFragmentLinkAt($rect->row, $rect->col));
                self::assertTrue($view->click($rect->row, $rect->col));
                self::assertSame($view->getById('target'), $view->getTarget());
            } finally {
                $view->unmount();
                $tui->stop();
            }
        }
    }
}
