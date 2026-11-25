<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\StyleSheet;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\LoaderWidget;
use Symfony\Component\Tui\Widget\ProgressBarWidget;

final class WidgetStateTest extends TestCase
{
    public function testCustomStates()
    {
        $widget = new TextWidget('a');
        $revision = $widget->getRenderRevision();
        $widget->setState('hover');
        $this->assertTrue($widget->hasState('hover'));
        $this->assertContains('hover', $widget->getStateFlags());
        $this->assertSame($revision + 1, $widget->getRenderRevision());
        $widget->setState('hover');
        $this->assertSame($revision + 1, $widget->getRenderRevision(), 'Setting a state twice does not invalidate.');
        $widget->setState('hover', false);
        $this->assertFalse($widget->hasState('hover'));
        $widget->setState('nope', false);
        $this->assertSame($revision + 2, $widget->getRenderRevision());
    }

    public function testBuiltInStatesCannotBeSet()
    {
        $widget = new InputWidget();
        foreach (['root', 'focus'] as $state) {
            foreach ([true, false] as $on) {
                try {
                    $widget->setState($state, $on);
                    $this->fail(\sprintf('The "%s" state follows the widget.', $state));
                } catch (InvalidArgumentException) {
                }
            }
        }
        $widget->setFocused(true);
        $this->assertSame(['root', 'focus'], $widget->getStateFlags());
        $widget->setFocused(false);
        $this->assertFalse($widget->hasState('focus'), 'The built-in focus state follows the widget.');
        $this->assertTrue($widget->hasState('root'));
    }

    public function testStateSelectorsInStyleSheets()
    {
        $widget = new TextWidget('a');
        $sheet = new StyleSheet(['.btn:hover' => new Style(null, null, null, null, true)]);
        $widget->addStyleClass('btn');
        $this->assertNull($sheet->resolve($widget)->getBold());
        $widget->setState('hover');
        $this->assertTrue($sheet->resolve($widget)->getBold());
    }

    public function testTheReducedMotionOfTheApplicationIsKept()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $tui->setReducedMotion();
        $view = (new HtmlBuilder())->build('<p>a</p>');
        $view->mount($tui);
        // Each pointer event binds the renderer to the TUI again
        $view->mount($tui);
        $this->assertTrue($tui->prefersReducedMotion());

        $tui->setMediaContext($tui->getMediaContext()->withSize(60, 10));
        $this->assertTrue($tui->prefersReducedMotion(), 'Only a change of reduced motion is given.');
    }

    public function testReducedMotionFreezesAnimations()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        // A view mounted, which ties the renderer to the TUI it reports the media of
        (new HtmlBuilder())->build('')->mount($tui);
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Bridge\TuiHtml\Style\ColorScheme::Dark, \Symfony\Bridge\TuiHtml\Style\Density::Regular, false, false, true));
        $this->assertTrue($tui->prefersReducedMotion());
        $tui->add($loader = new LoaderWidget('x'));
        $tui->add($bar = new ProgressBarWidget());
        $loader->setSpinner('line');
        $loader->setIntervalMs(80);
        $loader->start();
        $bar->start();
        $frame = $loader->getSpinnerFrame();
        $this->assertFalse($loader->tick(0.24));
        $this->assertSame($frame, $loader->getSpinnerFrame());
        $offset = $bar->getBarOffset();
        $bar->tick();
        $bar->tick();
        $this->assertSame($offset, $bar->getBarOffset());
        $tui->setMediaContext(new MediaContext());
        $this->assertTrue($loader->tick(0.24));
        $this->assertNotSame($frame, $loader->getSpinnerFrame());
        $bar->tick();
        $this->assertNotSame($offset, $bar->getBarOffset());
    }

    public function testMediaContextReachesProviders()
    {
        $terminal = new VirtualTerminal(50, 7);
        $tui = new Tui(null, $terminal);
        $css = CssStyleSheet::fromString('');
        $tui->addStyleProvider($css);
        $root = new ContainerWidget();
        $tui->add($root);
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Bridge\TuiHtml\Style\ColorScheme::Dark, \Symfony\Bridge\TuiHtml\Style\Density::Regular, false, false, true));
        $tui->start();
        $tui->processRender();
        $this->assertTrue($tui->getMediaContext()->reducedMotion);
        $this->assertSame(50, $tui->getMediaContext()->columns, 'The renderer keeps the terminal size up to date.');
        $this->assertSame(7, $tui->getMediaContext()->rows);
    }
}
