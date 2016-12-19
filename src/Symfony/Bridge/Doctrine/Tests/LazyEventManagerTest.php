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

use Symfony\Bridge\Doctrine\LazyEventManager;

class LazyEventManagerTest extends \PHPUnit_Framework_TestCase
{
    protected function createEventManager($id = null, $listener = null)
    {
        $resolver = $this->getMock('stdClass', array('__invoke'));

        if ($id && $listener) {
            $resolver
                ->method('__invoke')
                ->with($id)
                ->willReturn($listener)
            ;
        }

        return new LazyEventManager($resolver);
    }

    public function testDispatchEvent()
    {
        $evm = $this->createEventManager('foobar', $listener1 = new MyListener());

        $evm->addEventListener('foo', 'foobar');
        $evm->addEventListener('foo', $listener2 = new MyListener());

        $evm->dispatchEvent('foo');

        $this->assertTrue($listener1->called);
        $this->assertTrue($listener2->called);
    }

    public function testRemoveEventListener()
    {
        $evm = $this->createEventManager();

        $evm->addEventListener('foo', 'bar');
        $evm->addEventListener('foo', $listener = new MyListener());

        $listeners = array('foo' => array('_service_bar' => 'bar', spl_object_hash($listener) => $listener));
        $this->assertSame($listeners, $evm->getListeners());
        $this->assertSame($listeners['foo'], $evm->getListeners('foo'));

        $evm->removeEventListener('foo', $listener);
        $this->assertSame(array('_service_bar' => 'bar'), $evm->getListeners('foo'));

        $evm->removeEventListener('foo', 'bar');
        $this->assertSame(array(), $evm->getListeners('foo'));
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
