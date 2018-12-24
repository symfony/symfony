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
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegexp Unused binding "$quz" in service [\s\S]+ Invalid service ".*\\ParentNotExists": class NotExists not found\.
     */
    public function testMissingParent()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(ParentNotExists::class, ParentNotExists::class);
        $definition->setBindings(array('$quz' => '123'));

        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }

    public function testTypedReferenceSupport()
    {
        $container = new ContainerBuilder();

        $bindings = array(CaseSensitiveClass::class => new BoundArgument(new Reference('foo')));

        // Explicit service id
        $definition1 = $container->register('def1', NamedArgumentsDummy::class);
        $definition1->addArgument($typedRef = new TypedReference('bar', CaseSensitiveClass::class));
        $definition1->setBindings($bindings);

        $definition2 = $container->register('def2', NamedArgumentsDummy::class);
        $definition2->addArgument(new TypedReference(CaseSensitiveClass::class, CaseSensitiveClass::class));
        $definition2->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $this->assertEquals(array($typedRef), $container->getDefinition('def1')->getArguments());
        $this->assertEquals(array(new Reference('foo')), $container->getDefinition('def2')->getArguments());
    }

    public function testScalarSetter()
    {
        $container = new ContainerBuilder();

        $definition = $container->autowire('foo', ScalarSetter::class);
        $definition->setBindings(array('$defaultLocale' => 'fr'));

        (new AutowireRequiredMethodsPass())->process($container);
        (new ResolveBindingsPass())->process($container);

        $this->assertEquals(array(array('setDefaultLocale', array('fr'))), $definition->getMethodCalls());
    }

    public function testOverriddenBindings()
    {
        $container = new ContainerBuilder();

        $binding = new BoundArgument('bar');

        $container->register('foo', 'stdClass')
            ->setBindings(array('$foo' => clone $binding));
        $container->register('bar', 'stdClass')
            ->setBindings(array('$foo' => clone $binding));

        $container->register('foo', 'stdClass');

        (new ResolveBindingsPass())->process($container);

        $this->assertInstanceOf('stdClass', $container->get('foo'));
    }
}
