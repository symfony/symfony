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
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Widget\CheckboxWidget;

final class CheckboxWidgetTest extends TestCase
{
    public function testWithoutItsNativeAppearance()
    {
        $box = new CheckboxWidget(true);
        $box->setNativeAppearance(false);
        $this->assertSame([''], $box->render(new RenderContext(20, 1)), 'No box.');
        $box->setGeneratedContent('✓', ' done');
        $this->assertSame(['✓ done'], $box->render(new RenderContext(20, 1)), 'Only what CSS gives.');
    }

    public function testSpaceToggles()
    {
        $checkbox = new CheckboxWidget();
        $values = [];
        $checkbox->onChange(static function (ChangeEvent $event) use (&$values) {
            $values[] = $event->getValue();
        });
        $checkbox->handleInput(' ');
        $this->assertTrue($checkbox->isChecked());
        $this->assertTrue($checkbox->hasState('checked'), 'The checked state follows the value, for :checked.');
        $checkbox->handleInput(' ');
        $checkbox->handleInput("\r");
        $this->assertFalse($checkbox->isChecked());
        $this->assertFalse($checkbox->hasState('checked'));
        $this->assertSame(['1', '0'], $values);
    }

    public function testRendersTheBox()
    {
        $this->assertSame(['[ ]'], (new CheckboxWidget())->render(new RenderContext(10, 1)));
        $this->assertSame(['[x]'], (new CheckboxWidget(true))->render(new RenderContext(10, 1)));
    }

    public function testNarrowerThanItsBoxItDrawsAMark()
    {
        // Like the h-4 w-4 of Tailwind, two columns: the mark, not a box cut
        $this->assertSame(['☑'], (new CheckboxWidget(true))->render(new RenderContext(2, 1)));
        $this->assertSame(['☐'], (new CheckboxWidget())->render(new RenderContext(1, 1)));
    }

    public function testInitialStateAndValue()
    {
        $checkbox = new CheckboxWidget(true, 'yes');
        $this->assertTrue($checkbox->isChecked());
        $this->assertTrue($checkbox->hasState('checked'));
        $this->assertSame('yes', $checkbox->getValue());
    }

    public function testSettingWithoutDispatching()
    {
        $checkbox = new CheckboxWidget();
        $dispatched = false;
        $checkbox->onChange(static function () use (&$dispatched) {
            $dispatched = true;
        });
        $checkbox->setChecked(true, false);
        $checkbox->setChecked(true);
        $this->assertTrue($checkbox->isChecked());
        $this->assertFalse($dispatched, 'Nothing changed the second time.');
    }

    public function testADisabledCheckboxCannotBeToggled()
    {
        $checkbox = (new CheckboxWidget())->setState('disabled');
        $this->assertFalse($checkbox->toggle());
        $checkbox->handleInput(' ');
        $this->assertFalse($checkbox->isChecked());
    }
}
