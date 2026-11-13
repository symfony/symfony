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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\StyleSheet;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\InputWidget;
use Symfony\Component\TuiHtml\Widget\LoaderWidget;
use Symfony\Component\TuiHtml\Widget\ProgressBarWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;

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

    public function testBuiltInStatesAreNotDuplicated()
    {
        $widget = new InputWidget();
        $widget->setFocused(true);
        $widget->setState('focus')->setState('root');
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

    public function testReducedMotionFreezesAnimations()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, \Symfony\Component\TuiHtml\Style\Density::Regular, false, false, true));
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
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, \Symfony\Component\TuiHtml\Style\Density::Regular, false, false, true));
        $tui->start();
        $tui->processRender();
        $this->assertTrue($tui->getMediaContext()->reducedMotion);
        $this->assertSame(50, $tui->getMediaContext()->columns, 'The renderer keeps the terminal size up to date.');
        $this->assertSame(7, $tui->getMediaContext()->rows);
    }
}
