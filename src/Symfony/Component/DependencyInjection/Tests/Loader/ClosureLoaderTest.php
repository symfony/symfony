<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;

class ClosureLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new ClosureLoader(new ContainerBuilder());

        $this->assertTrue($loader->supports(function ($container) {}), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    public function testLoad()
    {
        $loader = new ClosureLoader($container = new ContainerBuilder(), 'some-env');

        $loader->load(function ($container, $env) {
            $container->setParameter('foo', 'foo');
            $container->setParameter('env', $env);
        });

        $this->assertSame(['foo' => 'foo', 'env' => 'some-env'], $container->getParameterBag()->all());
    }
}
