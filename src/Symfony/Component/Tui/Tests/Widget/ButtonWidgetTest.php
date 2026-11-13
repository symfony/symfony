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
use Symfony\Component\Tui\Event\PressEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\ButtonWidget;

final class ButtonWidgetTest extends TestCase
{
    public function testRendersItsLabel()
    {
        $this->assertSame(['[ Save ]'], (new ButtonWidget('Save'))->render(new RenderContext(40, 1)));
        $this->assertSame(['[ Sa...'], (new ButtonWidget('Save me'))->render(new RenderContext(7, 1)), 'Truncated to the width.');
    }

    public function testWithoutItsNativeAppearance()
    {
        $button = new ButtonWidget('OK');
        $button->setNativeAppearance(false);
        $this->assertSame(['OK'], $button->render(new RenderContext(20, 1)), 'No brackets.');

        $button->setGeneratedContent('< ', null);
        $this->assertSame(['< OK'], $button->render(new RenderContext(20, 1)), 'Generated content is still drawn.');

        $button->setNativeAppearance(true);
        $this->assertSame(['< OK ]'], $button->render(new RenderContext(20, 1)), 'Back: the bracket nothing replaces.');
    }

    public function testEnterAndSpacePress()
    {
        $button = new ButtonWidget('Save');
        $presses = 0;
        $button->onPress(function (PressEvent $event) use (&$presses, $button) {
            $this->assertSame($button, $event->getTarget());
            ++$presses;
        });

        $button->handleInput("\r");
        $button->handleInput(' ');
        $button->handleInput('x');

        $this->assertSame(2, $presses);
    }

    public function testADisabledButtonCannotBePressed()
    {
        $button = (new ButtonWidget('Save'))->setState('disabled');
        $pressed = false;
        $button->onPress(static function () use (&$pressed) {
            $pressed = true;
        });

        $this->assertFalse($button->press());
        $button->handleInput("\r");
        $this->assertFalse($pressed);
    }

    public function testTextIsSanitized()
    {
        $button = new ButtonWidget("Sa\e[31mve");
        $this->assertSame('Sa[31mve', $button->getText());

        $revision = $button->getRenderRevision();
        $button->setText('Sa[31mve');
        $this->assertSame($revision, $button->getRenderRevision(), 'Setting the same text does not invalidate.');
        $button->setText('Other');
        $this->assertSame('Other', $button->getText());
    }

    public function testOnInputTakesPrecedence()
    {
        $button = new ButtonWidget('Save');
        $pressed = false;
        $button->onPress(static function () use (&$pressed) {
            $pressed = true;
        });
        $button->onInput(static fn () => true);

        $button->handleInput("\r");

        $this->assertFalse($pressed);
    }
}
