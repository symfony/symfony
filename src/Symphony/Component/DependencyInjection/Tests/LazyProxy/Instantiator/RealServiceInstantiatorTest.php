<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\LazyProxy\Instantiator;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\LazyProxy\Instantiator\RealServiceInstantiator;

/**
 * Tests for {@see \Symphony\Component\DependencyInjection\Instantiator\RealServiceInstantiator}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RealServiceInstantiatorTest extends TestCase
{
    public function testInstantiateProxy()
    {
        $instantiator = new RealServiceInstantiator();
        $instance = new \stdClass();
        $container = $this->getMockBuilder('Symphony\Component\DependencyInjection\ContainerInterface')->getMock();
        $callback = function () use ($instance) {
            return $instance;
        };

        $this->assertSame($instance, $instantiator->instantiateProxy($container, new Definition(), 'foo', $callback));
    }
}
