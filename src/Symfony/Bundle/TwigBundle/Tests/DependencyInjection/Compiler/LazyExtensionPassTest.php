<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\YamlExtension;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\LazyExtensionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\UrlHelper;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;

class LazyExtensionPassTest extends TestCase
{
    public function testExtensionsWithDependenciesAreMadeLazy()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')])
            ->addMethodCall('addExtension', [new Reference('aliased_extension')])
            ->addMethodCall('addExtension', [new Reference('inline_dependency')])
            ->addMethodCall('addExtension', [new Reference('setter_dependency')])
            ->addMethodCall('addGlobal', ['app', new Reference('app')]);
        $container->register('extension', RoutingExtension::class)
            ->addArgument(new Reference('router'));
        $container->register('other_extension', RoutingExtension::class)
            ->addArgument(new Reference('router'));
        $container->setAlias('aliased_extension', 'other_extension');
        $container->register('inline_dependency', HttpFoundationExtension::class)
            ->addArgument(new Definition(UrlHelper::class, [new Reference('request_stack')]));
        $container->register('setter_dependency', ExtensionWithSetter::class)
            ->addMethodCall('setDependency', [new Reference('router')]);
        $container->register('app', \stdClass::class)
            ->addArgument(new Reference('request_stack'));

        (new LazyExtensionPass())->process($container);

        $this->assertTrue($container->getDefinition('extension')->isLazy());
        $this->assertTrue($container->getDefinition('other_extension')->isLazy());
        $this->assertTrue($container->getDefinition('inline_dependency')->isLazy());
        $this->assertTrue($container->getDefinition('setter_dependency')->isLazy());
        $this->assertFalse($container->getDefinition('app')->isLazy());
    }

    public function testExtensionsWithoutDependenciesAreNotMadeLazy()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('no_argument')])
            ->addMethodCall('addExtension', [new Reference('attribute')]);
        $container->register('no_argument', YamlExtension::class);
        $container->register('attribute', AttributeExtension::class)
            ->addArgument(\stdClass::class);

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('no_argument')->isLazy());
        $this->assertFalse($container->getDefinition('attribute')->isLazy());
    }

    public function testExplicitLazinessIsKept()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', RoutingExtension::class)
            ->addArgument(new Reference('router'))
            ->setLazy(false);

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('extension')->isLazy());
    }

    public function testExtensionsThatCannotBeLazyAreSkipped()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('factory')])
            ->addMethodCall('addExtension', [new Reference('internal_parent')])
            ->addMethodCall('addExtension', [new Reference('unknown_class')]);
        $container->register('factory', RoutingExtension::class)
            ->setFactory([new Reference('extension_factory'), 'create'])
            ->addArgument(new Reference('router'));
        $container->register('internal_parent', ExtensionWithInternalParent::class)
            ->addArgument(new Reference('router'));
        $container->register('unknown_class', 'Foo\Bar')
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('factory')->isLazy());
        $this->assertFalse($container->getDefinition('internal_parent')->isLazy());
        $this->assertFalse($container->getDefinition('unknown_class')->isLazy());
    }
}

class ExtensionWithInternalParent extends \ArrayObject
{
}

class ExtensionWithSetter extends AbstractExtension
{
    public function setDependency(object $dependency): void
    {
    }
}
