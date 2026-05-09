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
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Render\RenderRequestorInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\WidgetContext;
use Symfony\Component\Tui\Widget\WidgetTree;

class WidgetTreeTest extends TestCase
{
    private WidgetTree $tree;

    protected function setUp(): void
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $this->tree = new WidgetTree($tui, new Keybindings(), new FocusManager($this->createStub(RenderRequestorInterface::class)), new Renderer(), $terminal, $tui->getEventDispatcher());
    }

    public function testSetRootDetachesPreviousRoot()
    {
        $root1 = new ContainerWidget();
        $root2 = new ContainerWidget();

        $this->tree->setRoot($root1);
        $this->assertInstanceOf(WidgetContext::class, $root1->getContext());

        $this->tree->setRoot($root2);
        $this->assertNull($root1->getContext());
        $this->assertInstanceOf(WidgetContext::class, $root2->getContext());
    }

    public function testAttachRecursivelyAttachesChildren()
    {
        $container = new ContainerWidget();
        $child1 = new TextWidget('Child 1');
        $child2 = new TextWidget('Child 2');
        $container->add($child1);
        $container->add($child2);

        $this->tree->attach($container, null);

        $this->assertInstanceOf(WidgetContext::class, $child1->getContext());
        $this->assertInstanceOf(WidgetContext::class, $child2->getContext());
    }

    public function testDetachRecursivelyDetachesChildren()
    {
        $container = new ContainerWidget();
        $child1 = new TextWidget('Child 1');
        $child2 = new TextWidget('Child 2');
        $container->add($child1);
        $container->add($child2);

        $this->tree->attach($container, null);
        $this->tree->detach($container);

        $this->assertNull($container->getContext());
        $this->assertNull($child1->getContext());
        $this->assertNull($child2->getContext());
    }

    public function testDetachSubtreeOnlyAffectsSubtree()
    {
        $root = new ContainerWidget();
        $child1 = new TextWidget('Stay');
        $child2Container = new ContainerWidget();
        $grandchild = new TextWidget('Go');

        $root->add($child1);
        $root->add($child2Container);
        $child2Container->add($grandchild);

        $this->tree->attach($root, null);

        // Detach only the subtree
        $this->tree->detach($child2Container);

        $this->assertInstanceOf(WidgetContext::class, $root->getContext());
        $this->assertInstanceOf(WidgetContext::class, $child1->getContext());
        $this->assertNull($child2Container->getContext());
        $this->assertNull($grandchild->getContext());
    }

    public function testDetachClearsParent()
    {
        $parent = new ContainerWidget();
        $child = new TextWidget('Hello');

        $this->tree->attach($child, $parent);
        $this->tree->detach($child);

        $this->assertNull($child->getParent());
    }
}
