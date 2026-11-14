<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\InputWidget;

final class FormValidityTest extends TestCase
{
    public function testFormsAndFieldsetsAreValidLikeTheirControls()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<style>form:invalid button { opacity: 0.5 } fieldset:valid { color: #00ff00 }</style>
            <form id="form"><fieldset id="who"><input id="name" required aria-label="Name"></fieldset><fieldset id="extra"><input aria-label="Note"></fieldset><button id="send">Send</button></form>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $sheet = $view->getStyleSheet();
        $this->assertSame('0.5', $sheet->getComputedValue($view->getById('send'), 'opacity'), 'An empty required field: the form is invalid.');
        $this->assertNull($sheet->getComputedValue($view->getById('who'), 'color'), 'Its fieldset too.');
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('extra'), 'color'), 'The other one is valid.');
        $name = $view->getById('name');
        $this->assertInstanceOf(InputWidget::class, $name);
        $name->setValue('Ada', true);
        $tui->requestRender();
        $tui->processRender();
        $this->assertNull($sheet->getComputedValue($view->getById('send'), 'opacity'), 'Filled in: valid.');
        $this->assertSame('#00ff00', $sheet->getComputedValue($view->getById('who'), 'color'));
    }
}
