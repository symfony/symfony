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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

class CheckFactoryBuilderCircularReferencePassTest extends TestCase
{
    public function testThrowsWhenInlinedConsumerReferencesProduct()
    {
        // The consumer ("extension") is private and used only by the builder's
        // method call, so it gets inlined into the builder. The dumper would emit
        // "$instance = $a->build()" before "$a->useExtension(...)" and silently
        // produce a half-built service.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass')
            ->addMethodCall('useExtension', [new Reference('extension')]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);
        $container->register('extension', 'stdClass')
            ->addArgument(new Reference('product'));

        $this->expectException(ServiceCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for service "product"');

        $container->compile();
    }

    public function testThrowsWhenPublicConsumerReferencesProduct()
    {
        // The consumer ("extension") is public, so it stays a Reference inside
        // the inlined builder's setter args. Same broken pattern, different graph
        // shape: the previous dumper-side check missed this one.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass')
            ->addMethodCall('useExtension', [new Reference('extension')]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);
        $container->register('extension', 'stdClass')
            ->setPublic(true)
            ->addArgument(new Reference('product'));

        $this->expectException(ServiceCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for service "product", path: "product -> extension -> product"');

        $container->compile();
    }

    public function testAllowsBuilderWithoutSetup()
    {
        // The factory builder has no method calls/properties/configurator, so the
        // soft-circular pattern works: the cycle is on the produced service's own
        // setter, not on the builder.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass');
        $container->register('product', 'stdClass')
            ->setPublic(true)
            ->setFactory([new Reference('builder'), 'build'])
            ->addMethodCall('setExtension', [new Reference('extension')]);
        $container->register('extension', 'stdClass')
            ->addArgument(new Reference('product'));

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testAllowsStringFactoryWithSelfSetterCycle()
    {
        $container = new ContainerBuilder();
        $container->register('product', 'stdClass')
            ->setPublic(true)
            ->setFactory('create_product')
            ->addMethodCall('setExtension', [new Reference('extension')]);
        $container->register('extension', 'stdClass')
            ->addArgument(new Reference('product'));

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testAllowsConstructorArgInlinedSetterCycle()
    {
        // FooCircular-style: foo's constructor takes bar; bar's setter takes foobar;
        // foobar's constructor takes foo. The inlined sub-Definition (bar) is a
        // *constructor arg*, not a factory, so foo holds bar and bar's deferred
        // setter is observable through that reference. Legit soft-circular.
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')
            ->setPublic(true)
            ->addArgument(new Reference('bar'));
        $container->register('bar', 'stdClass')
            ->addMethodCall('addFoobar', [new Reference('foobar')]);
        $container->register('foobar', 'stdClass')
            ->addArgument(new Reference('foo'));

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testIgnoresLazyConsumer()
    {
        // The consumer is lazy: it gets a proxy, the eager construction chain is
        // broken. The same shape as Case B but with `extension` lazy.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass')
            ->addMethodCall('useExtension', [new Reference('extension')]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);
        $container->register('extension', 'stdClass')
            ->setPublic(true)
            ->setLazy(true)
            ->addArgument(new Reference('product'));

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testFollowsTransitiveConstructorChain()
    {
        // builder's setter doesn't reference product directly, but the chain
        // builder -> mid -> product still requires product before the factory
        // call. Same shape, one hop further.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass')
            ->addMethodCall('useMid', [new Reference('mid')]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);
        $container->register('mid', 'stdClass')
            ->setPublic(true)
            ->addArgument(new Reference('extension'));
        $container->register('extension', 'stdClass')
            ->setPublic(true)
            ->addArgument(new Reference('product'));

        $this->expectException(ServiceCircularReferenceException::class);

        $container->compile();
    }

    public function testIgnoresInlinedDefinitionFactoryWithoutCycle()
    {
        // The factory builder has setters but they don't lead back to the
        // produced service. Should compile cleanly.
        $container = new ContainerBuilder();
        $container->register('builder', 'stdClass')
            ->addMethodCall('useUnrelated', [new Reference('unrelated')]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);
        $container->register('unrelated', 'stdClass')
            ->setPublic(true);

        $container->compile();

        $this->addToAssertionCount(1);
    }

    public function testInlinedExtensionDefinitionRouteAlsoCaught()
    {
        // Same as Case A but using a literal Definition argument (mimicking what
        // InlineServiceDefinitionsPass produces) so we exercise the
        // walk-Definition path of the pass.
        $container = new ContainerBuilder();
        $extension = new Definition('stdClass');
        $extension->addArgument(new Reference('product'));
        $container->register('builder', 'stdClass')
            ->addMethodCall('useExtension', [$extension]);
        $container->register('product', 'stdClass')
            ->setFactory([new Reference('builder'), 'build'])
            ->setPublic(true);

        $this->expectException(ServiceCircularReferenceException::class);

        $container->compile();
    }
}
