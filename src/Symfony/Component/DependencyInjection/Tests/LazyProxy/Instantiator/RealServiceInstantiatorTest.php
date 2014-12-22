<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\LazyProxy\Instantiator;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\RealServiceInstantiator;

/**
 * Tests for {@see \Symfony\Component\DependencyInjection\Instantiator\RealServiceInstantiator}.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @covers \Symfony\Component\DependencyInjection\LazyProxy\Instantiator\RealServiceInstantiator
 */
class RealServiceInstantiatorTest extends \PHPUnit_Framework_TestCase
{
    public function testInstantiateProxy()
    {
        $instantiator = new RealServiceInstantiator();
        $instance = new \stdClass();
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $callback = function () use ($instance) {
            return $instance;
        };

        $this->assertSame($instance, $instantiator->instantiateProxy($container, new Definition(), 'foo', $callback));
    }
}
