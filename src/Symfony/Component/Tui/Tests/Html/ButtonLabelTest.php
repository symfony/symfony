<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ButtonWidget;

final class ButtonLabelTest extends TestCase
{
    public function testTheLabelFollowsTheStatesOfAnAncestorPastWrappersOfTheSameName()
    {
        // Wrappers of no class between and around: each paired with its own, not with the card further up
        $view = (new HtmlBuilder(new CssStyleSheet(strict: false)))->build('<style>.label { display: none } .card:hover .label { display: inline }</style><div><div class="card" id="card"><div><p>Text <button id="b"><span class="label">Edit </span>it</button></p></div></div></div>');
        $tui = new Tui(terminal: new VirtualTerminal(60, 10));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $button = $view->getById('b');
        $this->assertInstanceOf(ButtonWidget::class, $button);
        $this->assertSame('it', $button->getText());

        $view->getById('card')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('Edit it', $button->getText());
        $tui->stop();
    }

    public function testTheLabelFollowsTheStatesOfAnAncestor()
    {
        $view = (new HtmlBuilder(new CssStyleSheet(strict: false)))->build('<style>.label { display: none } .card:hover .label { display: inline }</style><div class="card" id="card"><p>Text <button id="b"><span class="label">Edit </span>it</button></p></div>');
        $tui = new Tui(terminal: new VirtualTerminal(60, 10));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $button = $view->getById('b');
        $this->assertInstanceOf(ButtonWidget::class, $button);
        $this->assertSame('it', $button->getText());

        $view->getById('card')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('Edit it', $button->getText(), 'The card hovered: the label displayed.');

        $view->getById('card')->setState('hover', false);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('it', $button->getText(), 'No longer hovered: hidden again.');
        $tui->stop();
    }

    public function testAButtonRemovedDoesNotHoldItsDocument()
    {
        $styleSheet = new CssStyleSheet(strict: false);
        $view = (new HtmlBuilder($styleSheet))->build('<style>.label { display: none } @media (min-width: 40ch) { .label { display: inline } }</style><div><button id="b"><span class="label">Edit </span>it</button></div><p>Other</p>')->computeStyles();
        // The element of the label, which only what follows the button holds
        $label = null;
        foreach ((new \ReflectionProperty(CssStyleSheet::class, 'displayFollowers'))->getValue($styleSheet) as $entry) {
            $label = \WeakReference::create($entry[1][0]);
        }
        unset($entry);
        $this->assertNotNull($label?->get());

        $button = $view->getById('b');
        $button->getParent()->remove($button);
        $button = null;
        // The cycles freed free others: until none is left
        while (gc_collect_cycles() > 0) {
        }
        $view->computeStyles();
        while (gc_collect_cycles() > 0) {
        }

        $this->assertNull($label->get(), 'The elements of the document of a button removed are not held.');
    }
}
