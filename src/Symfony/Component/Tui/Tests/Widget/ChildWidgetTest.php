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
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class ChildWidgetTest extends TestCase
{
    public function testAttachChildSetsTheParent()
    {
        $parent = new ChildWidgetTest_Owner();
        $child = new TextWidget('child');

        $this->assertNull($child->getParent());

        $parent->own($child);

        $this->assertSame($parent, $child->getParent());
    }

    public function testDetachChildClearsTheParent()
    {
        $parent = new ChildWidgetTest_Owner();
        $child = new TextWidget('child');

        $parent->own($child);
        $parent->release($child);

        $this->assertNull($child->getParent());
    }

    public function testChildrenCanBeWiredBeforeTheOwnerIsAttached()
    {
        $parent = new ChildWidgetTest_Owner();
        $child = new TextWidget('child');

        $this->assertNull($parent->getContext());

        $parent->own($child);
        $parent->release($child);

        $this->addToAssertionCount(1);
    }
}

class ChildWidgetTest_Owner extends AbstractWidget
{
    public function own(AbstractWidget $child): void
    {
        $this->attachChild($child);
    }

    public function release(AbstractWidget $child): void
    {
        $this->detachChild($child);
    }

    public function render(RenderContext $context): array
    {
        return [];
    }
}
