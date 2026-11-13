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
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

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
}
