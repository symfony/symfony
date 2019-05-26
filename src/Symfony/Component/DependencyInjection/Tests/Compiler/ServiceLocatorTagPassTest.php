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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition2;

require_once __DIR__.'/../Fixtures/includes/classes.php';

class ServiceLocatorTagPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "foo": an array of references is expected as first argument when the "container.service_locator" tag is set.
     */
    public function testNoServices()
    {
        $container = new ContainerBuilder();

        $container->register('foo', ServiceLocator::class)
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid definition for service "foo": an array of references is expected as first argument when the "container.service_locator" tag is set, "string" found for key "0".
     */
    public function testInvalidServices()
    {
        $container = new ContainerBuilder();

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                'dummy',
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);
    }

    public function testProcessValue()
    {
        $container = new ContainerBuilder();

        $container->register('bar', CustomDefinition::class);
        $container->register('baz', CustomDefinition::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                new Reference('bar'),
                new Reference('baz'),
                'some.service' => new Reference('bar'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(CustomDefinition::class, \get_class($locator('bar')));
        $this->assertSame(CustomDefinition::class, \get_class($locator('baz')));
        $this->assertSame(CustomDefinition::class, \get_class($locator('some.service')));
    }

    public function testServiceWithKeyOverwritesPreviousInheritedKey()
    {
        $container = new ContainerBuilder();

        $container->register('bar', TestDefinition1::class);
        $container->register('baz', TestDefinition2::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                new Reference('bar'),
                'bar' => new Reference('baz'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(TestDefinition2::class, \get_class($locator('bar')));
    }

    public function testInheritedKeyOverwritesPreviousServiceWithKey()
    {
        $container = new ContainerBuilder();

        $container->register('bar', TestDefinition1::class);
        $container->register('baz', TestDefinition2::class);

        $container->register('foo', ServiceLocator::class)
            ->setArguments([[
                'bar' => new Reference('baz'),
                new Reference('bar'),
            ]])
            ->addTag('container.service_locator')
        ;

        (new ServiceLocatorTagPass())->process($container);

        /** @var ServiceLocator $locator */
        $locator = $container->get('foo');

        $this->assertSame(TestDefinition1::class, \get_class($locator('bar')));
    }

    public function testBindingsAreCopied()
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setBindings(['foo' => 'foo']);

        $locator = ServiceLocatorTagPass::register($container, ['foo' => new Reference('foo')], 'foo');
        $locator = $container->getDefinition($locator);
        $locator = $container->getDefinition($locator->getFactory()[0]);

        $this->assertSame(['foo'], array_keys($locator->getBindings()));
        $this->assertInstanceOf(BoundArgument::class, $locator->getBindings()['foo']);
    }
}
