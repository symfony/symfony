<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Debug;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Debug\TraceableEventDispatcher;

class TraceableEventDispactherTest extends TestCase
{

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenAListenerMethodIsNotCallable()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new TraceableEventDispatcher($container);
        $dispatcher->addListener('onFooEvent', new \stdClass());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenAListenerServiceIsNotFound()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('listener.service'))
            ->will($this->returnValue(false))
        ;

        $dispatcher = new TraceableEventDispatcher($container);

        $dispatcher->addListenerService('onFooEvent', 'listener.service');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenAListenerServiceMethodIsNotCallable()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('listener.service'))
            ->will($this->returnValue(true))
        ;
        $container
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('listener.service'))
            ->will($this->returnValue(new \stdClass()))
        ;

        $dispatcher = new TraceableEventDispatcher($container);
        $dispatcher->addListenerService('onFooEvent', 'listener.service');
    }
}
