<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\Container;

class ContainerAwareEventManagerTest extends TestCase
{
    private $container;
    private $evm;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->evm = new ContainerAwareEventManager($this->container);
    }

    public function testDispatchEvent()
    {
        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->container->set('lazy2', $listener3 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy2');
        $this->evm->addEventListener('bar', $listener4 = new MyListener());
        $this->container->set('lazy3', $listener5 = new MyListener());
        $this->evm->addEventListener('foo', $listener5 = new MyListener());
        $this->evm->addEventListener('bar', $listener5);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(1, $listener1->calledByEventNameCount);
        $this->assertSame(0, $listener2->calledByInvokeCount);
        $this->assertSame(1, $listener2->calledByEventNameCount);
        $this->assertSame(1, $listener3->calledByInvokeCount);
        $this->assertSame(0, $listener3->calledByEventNameCount);
        $this->assertSame(1, $listener4->calledByInvokeCount);
        $this->assertSame(0, $listener4->calledByEventNameCount);
        $this->assertSame(1, $listener5->calledByInvokeCount);
        $this->assertSame(1, $listener5->calledByEventNameCount);
    }

    public function testAddEventListenerAfterDispatchEvent()
    {
        $this->container->set('lazy1', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy1');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());
        $this->container->set('lazy2', $listener3 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy2');
        $this->evm->addEventListener('bar', $listener4 = new MyListener());
        $this->container->set('lazy3', $listener5 = new MyListener());
        $this->evm->addEventListener('foo', $listener5 = new MyListener());
        $this->evm->addEventListener('bar', $listener5);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->container->set('lazy4', $listener6 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy4');
        $this->evm->addEventListener('foo', $listener7 = new MyListener());
        $this->container->set('lazy5', $listener8 = new MyListener());
        $this->evm->addEventListener('bar', 'lazy5');
        $this->evm->addEventListener('bar', $listener9 = new MyListener());
        $this->container->set('lazy6', $listener10 = new MyListener());
        $this->evm->addEventListener('foo', $listener10 = new MyListener());
        $this->evm->addEventListener('bar', $listener10);

        $this->evm->dispatchEvent('foo');
        $this->evm->dispatchEvent('bar');

        $this->assertSame(0, $listener1->calledByInvokeCount);
        $this->assertSame(2, $listener1->calledByEventNameCount);
        $this->assertSame(0, $listener2->calledByInvokeCount);
        $this->assertSame(2, $listener2->calledByEventNameCount);
        $this->assertSame(2, $listener3->calledByInvokeCount);
        $this->assertSame(0, $listener3->calledByEventNameCount);
        $this->assertSame(2, $listener4->calledByInvokeCount);
        $this->assertSame(0, $listener4->calledByEventNameCount);
        $this->assertSame(2, $listener5->calledByInvokeCount);
        $this->assertSame(2, $listener5->calledByEventNameCount);

        $this->assertSame(0, $listener6->calledByInvokeCount);
        $this->assertSame(1, $listener6->calledByEventNameCount);
        $this->assertSame(0, $listener7->calledByInvokeCount);
        $this->assertSame(1, $listener7->calledByEventNameCount);
        $this->assertSame(1, $listener8->calledByInvokeCount);
        $this->assertSame(0, $listener8->calledByEventNameCount);
        $this->assertSame(1, $listener9->calledByInvokeCount);
        $this->assertSame(0, $listener9->calledByEventNameCount);
        $this->assertSame(1, $listener10->calledByInvokeCount);
        $this->assertSame(1, $listener10->calledByEventNameCount);
    }

    public function testGetListenersForEvent()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners('foo')));
    }

    public function testGetListeners()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->assertSame([$listener1, $listener2], array_values($this->evm->getListeners()['foo']));
    }

    public function testRemoveEventListener()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->evm->removeEventListener('foo', $listener2);
        $this->assertSame([$listener1], array_values($this->evm->getListeners('foo')));

        $this->evm->removeEventListener('foo', 'lazy');
        $this->assertSame([], $this->evm->getListeners('foo'));
    }

    public function testRemoveEventListenerAfterDispatchEvent()
    {
        $this->container->set('lazy', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'lazy');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->evm->dispatchEvent('foo');

        $this->evm->removeEventListener('foo', $listener2);
        $this->assertSame([$listener1], array_values($this->evm->getListeners('foo')));

        $this->evm->removeEventListener('foo', 'lazy');
        $this->assertSame([], $this->evm->getListeners('foo'));
    }
}

class MyListener
{
    public $calledByInvokeCount = 0;
    public $calledByEventNameCount = 0;

    public function __invoke(): void
    {
        ++$this->calledByInvokeCount;
    }

    public function foo()
    {
        ++$this->calledByEventNameCount;
    }
}
