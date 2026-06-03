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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInvalidReferencesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

class ResolveInvalidReferencesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setArguments([
                new Reference('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('baz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->addMethodCall('foo', [new Reference('moo', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertSame([null, null], $arguments);
        $this->assertCount(0, $def->getMethodCalls());
    }

    public function testProcessIgnoreInvalidArgumentInCollectionArgument()
    {
        $container = new ContainerBuilder();
        $container->register('baz');
        $def = $container
            ->register('foo')
            ->setArguments([
                [
                    new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    $baz = new Reference('baz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    new Reference('moo', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ],
            ])
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertSame([$baz, null], $arguments[0]);
    }

    public function testProcessKeepMethodCallOnInvalidArgumentInCollectionArgument()
    {
        $container = new ContainerBuilder();
        $container->register('baz');
        $def = $container
            ->register('foo')
            ->addMethodCall('foo', [
                [
                    new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    $baz = new Reference('baz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    new Reference('moo', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ],
            ])
        ;

        $this->process($container);

        $calls = $def->getMethodCalls();
        $this->assertCount(1, $def->getMethodCalls());
        $this->assertSame([$baz, null], $calls[0][1][0]);
    }

    public function testProcessIgnoreNonExistentServices()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setArguments([new Reference('bar')])
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertEquals('bar', (string) $arguments[0]);
    }

    public function testProcessRemovesPropertiesOnInvalid()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setProperty('foo', new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
        ;

        $this->process($container);

        $this->assertEquals([], $def->getProperties());
    }

    public function testProcessRemovesArgumentsOnInvalid()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->addArgument([
                [
                    new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    new ServiceClosureArgument(new Reference('baz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
                ],
            ])
        ;

        $this->process($container);

        $this->assertSame([[[]]], $def->getArguments());
    }

    public function testProcessSetDecoratedAsNullOnInvalid()
    {
        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setArguments([
                new Reference('decorator.inner'),
            ])
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        (new DecoratorServicePass())->process($container);
        (new ResolveInvalidReferencesPass())->process($container);

        $this->assertSame([null], $decoratorDefinition->getArguments());
    }

    public function testProcessSetOnlyDecoratedAsNullOnInvalid()
    {
        $container = new ContainerBuilder();
        $unknownArgument = new Reference('unknown_argument');
        $decoratorDefinition = $container
            ->register('decorator')
            ->setArguments([
                new Reference('decorator.inner'),
                $unknownArgument,
            ])
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        (new DecoratorServicePass())->process($container);
        (new ResolveInvalidReferencesPass())->process($container);

        $this->assertNull($decoratorDefinition->getArguments()[0]);
        $this->assertEquals($unknownArgument, $decoratorDefinition->getArguments()[1]);
    }

    public function testProcessExcludedServiceAndNullOnInvalid()
    {
        $container = new ContainerBuilder();
        $container->register('foo', \stdClass::class)->addTag('container.excluded');
        $container->register('bar', \stdClass::class)->addArgument(new Reference('foo', $container::NULL_ON_INVALID_REFERENCE));

        $this->process($container);

        $this->assertSame([null], $container->getDefinition('bar')->getArguments());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveInvalidReferencesPass();
        $pass->process($container);
    }
}
