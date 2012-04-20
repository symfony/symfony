<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Debug;

use Symfony\Component\HttpKernel\Debug\ContainerAwareTraceableEventDispatcher;
use Symfony\Component\HttpKernel\Debug\Stopwatch;

class ContainerAwareTraceableEventDispatcherTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\DependencyInjection\Container')) {
            $this->markTestSkipped('The "DependencyInjection" component is not available');
        }

        if (!class_exists('Symfony\Component\HttpKernel\HttpKernel')) {
            $this->markTestSkipped('The "HttpKernel" component is not available');
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionWhenAListenerMethodIsNotCallable()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new ContainerAwareTraceableEventDispatcher($container, new Stopwatch());
        $dispatcher->addListener('onFooEvent', new \stdClass());
    }

    public function testClosureDoesNotTriggerErrorNotice()
    {
        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new ContainerAwareTraceableEventDispatcher($container, new StopWatch());
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

    public function testStaticCallable()
    {
        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new ContainerAwareTraceableEventDispatcher($container, new StopWatch());

        $dispatcher->addListener('onFooEvent', array(__NAMESPACE__.'\StaticClassFixture', 'staticListener'));

        $dispatcher->dispatch('onFooEvent');

        $this->assertTrue(StaticClassFixture::$called);
    }
}

class StaticClassFixture
{
    static public $called = false;

    static public function staticListener($event)
    {
        self::$called = true;
    }
}
