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

use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\Container;

class ContainerAwareEventManagerTest extends \PHPUnit_Framework_TestCase
{
    private $container;
    private $evm;

    protected function setUp()
    {
        $this->container = new Container();
        $this->evm = new ContainerAwareEventManager($this->container);
    }

    public function testDispatchEvent()
    {
        $this->container->set('foobar', $listener1 = new MyListener());
        $this->evm->addEventListener('foo', 'foobar');
        $this->evm->addEventListener('foo', $listener2 = new MyListener());

        $this->evm->dispatchEvent('foo');

        $this->assertTrue($listener1->called);
        $this->assertTrue($listener2->called);
    }

    public function testRemoveEventListener()
    {
        $this->evm->addEventListener('foo', 'bar');
        $this->evm->addEventListener('foo', $listener = new MyListener());

        $listeners = array('foo' => array('_service_bar' => 'bar', spl_object_hash($listener) => $listener));
        $this->assertSame($listeners, $this->evm->getListeners());
        $this->assertSame($listeners['foo'], $this->evm->getListeners('foo'));

        $this->evm->removeEventListener('foo', $listener);
        $this->assertSame(array('_service_bar' => 'bar'), $this->evm->getListeners('foo'));

        $this->evm->removeEventListener('foo', 'bar');
        $this->assertSame(array(), $this->evm->getListeners('foo'));
    }
}

class MyListener
{
    public $called = false;

    public function foo()
    {
        $this->called = true;
    }
}
