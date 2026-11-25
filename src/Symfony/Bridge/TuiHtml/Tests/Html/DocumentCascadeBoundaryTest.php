<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

final class DocumentCascadeBoundaryTest extends TestCase
{
    public function testAFramesInheritedStylesDoNotBecomeDeclarationsOfTheNestedDocument()
    {
        $view = new HtmlBuilder()->build('<!doctype html><style>iframe{color:red;font-weight:700;font-style:italic}</style><iframe srcdoc="<p id=inner>nested text</p>"></iframe>');
        $nested = $view->getFrames()[0];
        $tui = new HtmlTui(null, new VirtualTerminal(40, 10));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            self::assertSame([], $view->getStyleSheet()->getStyles($nested->getById('inner')));
            self::assertNull($view->getStyleSheet()->getComputedValue($nested->getById('inner'), 'font-weight'));
        } finally {
            $tui->stop();
        }
    }
}
