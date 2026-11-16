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
use Symfony\Component\Tui\Render\ArrayLineBuffer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class ChangedChildrenTest extends TestCase
{
    public function testTheChildrenChangedSinceASerialAreKnownTheLastChangedFirst()
    {
        $container = new ContainerWidget();
        $container->add($a = new TextWidget('a'))->add($b = new TextWidget('b'))->add($c = new TextWidget('c'));
        $serial = AbstractWidget::getChangeSerial();
        $this->assertNull($container->getChildrenChangedSince($serial), 'Not kept before they are asked for');
        $this->assertSame([], $container->getChildrenChangedSince($serial));

        $c->setText('C');
        $a->setText('A');
        $c->setText('CC');
        $this->assertSame([$c, $a], $container->getChildrenChangedSince($serial));
        $this->assertNotContains($b, $container->getChildrenChangedSince($serial));

        $later = AbstractWidget::getChangeSerial();
        $b->setText('B');
        $this->assertSame([$b], $container->getChildrenChangedSince($later));
        $this->assertSame([], $container->getChildrenChangedSince(AbstractWidget::getChangeSerial()));
    }

    public function testAChangeOfADescendantIsOneOfItsChild()
    {
        $container = new ContainerWidget();
        $container->add($child = new ContainerWidget())->add(new TextWidget('b'));
        $child->add($grandChild = new TextWidget('a'));
        $serial = AbstractWidget::getChangeSerial();
        $container->getChildrenChangedSince($serial);
        $child->getChildrenChangedSince($serial);

        $grandChild->setText('A');

        $this->assertSame([$child], $container->getChildrenChangedSince($serial));
        $this->assertSame([$grandChild], $child->getChildrenChangedSince($serial));
    }

    public function testARenderStoredOrClearedIsAChange()
    {
        $container = new ContainerWidget();
        $container->add($a = new TextWidget('a'))->add($b = new TextWidget('b'));
        $serial = AbstractWidget::getChangeSerial();
        $container->getChildrenChangedSince($serial);

        $a->setRenderCache(new ArrayLineBuffer(['a']), 10, 1);
        $this->assertSame([$a], $container->getChildrenChangedSince($serial));

        $serial = AbstractWidget::getChangeSerial();
        $b->clearRenderCache();
        $this->assertSame([$b], $container->getChildrenChangedSince($serial));
    }

    public function testAChangeOfTheWidgetItselfLeavesItsChildrenUnknown()
    {
        $container = new ContainerWidget();
        $container->add($a = new TextWidget('a'));
        $serial = AbstractWidget::getChangeSerial();
        $container->getChildrenChangedSince($serial);

        $container->add(new TextWidget('b'));
        $this->assertNull($container->getChildrenChangedSince($serial));

        $serial = AbstractWidget::getChangeSerial();
        $container->remove($a);
        $this->assertNull($container->getChildrenChangedSince($serial));

        $serial = AbstractWidget::getChangeSerial();
        $container->setStyle(new Style(gap: 1));
        $this->assertNull($container->getChildrenChangedSince($serial));
        $this->assertSame([], $container->getChildrenChangedSince(AbstractWidget::getChangeSerial()));
    }

    public function testARemovedChildIsNotKeptAlive()
    {
        $container = new ContainerWidget();
        $container->add($child = new TextWidget('a'));
        $container->getChildrenChangedSince(AbstractWidget::getChangeSerial());
        $child->setText('A');
        $container->remove($child);
        $reference = \WeakReference::create($child);
        unset($child);

        $this->assertNull($reference->get());
    }
}
