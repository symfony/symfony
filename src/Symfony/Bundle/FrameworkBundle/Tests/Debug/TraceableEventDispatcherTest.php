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

class TraceableEventDispatcherTest extends TestCase
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

    public function testStaticCallable()
    {
        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $dispatcher = new TraceableEventDispatcher($container);

        $dispatcher->addListener('onFooEvent', array(__NAMESPACE__.'\StaticClassFixture', 'staticListener'));

        $dispatcher->dispatch('onFooEvent');

        $this->assertTrue(StaticClassFixture::$called);
    }
}

class StaticClassFixture
{
    public static $called = false;

    public static function staticListener($event)
    {
        self::$called = true;
    }
}
