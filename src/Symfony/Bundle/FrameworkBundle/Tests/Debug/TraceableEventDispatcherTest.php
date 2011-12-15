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
use Symfony\Component\HttpKernel\Debug\Stopwatch;

class TraceableEventDispatcherTest extends TestCase
{

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenAListenerMethodIsNotCallable()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new TraceableEventDispatcher($container, new Stopwatch());
        $dispatcher->addListener('onFooEvent', new \stdClass());
    }

    public function testClosureDoesNotTriggerErrorNotice()
    {
        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new TraceableEventDispatcher($container, new StopWatch());
        $triggered  = false;

        $dispatcher->addListener('onFooEvent', function() use (&$triggered) {
            $triggered = true;
        });

        try {
            $dispatcher->dispatch('onFooEvent');
        } catch (\PHPUnit_Framework_Error_Notice $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue($triggered, 'Closure should have been executed upon dispatch');
    }

}
