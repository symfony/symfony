<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;

class ResolveBindingsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $bindings = array(CaseSensitiveClass::class => new BoundArgument(new Reference('foo')));

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array(1 => '123'));
        $definition->addMethodCall('setSensitiveClass');
        $definition->setBindings($bindings);

        $container->register('foo', CaseSensitiveClass::class)
            ->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('foo'), '123'), $definition->getArguments());
        $this->assertEquals(array(array('setSensitiveClass', array(new Reference('foo')))), $definition->getMethodCalls());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unused binding "$quz" in service "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy".
     */
    public function testUnusedBinding()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setBindings(array('$quz' => '123'));

        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }
}
