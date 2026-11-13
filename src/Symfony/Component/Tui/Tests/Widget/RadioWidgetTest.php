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
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\RadioGroup;
use Symfony\Component\Tui\Widget\RadioWidget;

final class RadioWidgetTest extends TestCase
{
    public function testCheckingOneUnchecksTheOthersOfTheGroup()
    {
        $group = new RadioGroup('size');
        $small = new RadioWidget(false, 's', $group);
        $large = new RadioWidget(true, 'l', $group);
        $events = [];
        foreach ([$small, $large] as $radio) {
            $radio->onChange(static function (ChangeEvent $event) use (&$events) {
                $events[] = [$event->getTarget()->getValue(), $event->getValue()];
            });
        }

        $this->assertSame($large, $group->getChecked());

        $small->handleInput(' ');

        $this->assertTrue($small->isChecked());
        $this->assertFalse($large->isChecked());
        $this->assertFalse($large->hasState('checked'));
        $this->assertSame($small, $group->getChecked());
        $this->assertSame([['l', '0'], ['s', '1']], $events);
        $this->assertSame([$small, $large], $group->all());
        $this->assertSame('size', $group->name);
    }

    public function testRadiosAreIndeterminateWhileNoneOfTheGroupIsChecked()
    {
        $group = new RadioGroup('size');
        $small = new RadioWidget(false, 's', $group);
        $large = new RadioWidget(false, 'l', $group);
        $this->assertTrue($small->hasState('indeterminate'));
        $this->assertTrue($large->hasState('indeterminate'));

        $large->setChecked(true);
        $this->assertFalse($small->hasState('indeterminate'));
        $this->assertFalse($large->hasState('indeterminate'));

        $this->assertFalse((new RadioWidget(false, 'm', $group))->hasState('indeterminate'), 'A radio joining a group with a checked one');

        $alone = new RadioWidget();
        $this->assertTrue($alone->hasState('indeterminate'));
        $alone->setChecked(true);
        $this->assertFalse($alone->hasState('indeterminate'));
    }

    public function testPressingACheckedRadioKeepsItChecked()
    {
        $radio = new RadioWidget(true);

        $this->assertFalse($radio->toggle());
        $this->assertTrue($radio->isChecked());
    }

    public function testCheckedAtCreationUnchecksTheOthers()
    {
        $group = new RadioGroup();
        $first = new RadioWidget(true, 'a', $group);
        $second = new RadioWidget(true, 'b', $group);

        $this->assertFalse($first->isChecked());
        $this->assertTrue($second->isChecked());
    }

    public function testRendersTheCircle()
    {
        $this->assertSame(['( )'], (new RadioWidget())->render(new RenderContext(10, 1)));
        $this->assertSame(['(•)'], (new RadioWidget(true))->render(new RenderContext(10, 1)));
        $this->assertNull((new RadioWidget())->getGroup());
    }

    public function testADisabledRadioCannotBeChecked()
    {
        $radio = (new RadioWidget())->setState('disabled');

        $radio->handleInput(' ');

        $this->assertFalse($radio->isChecked());
    }
}
