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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorServicePassTest extends TestCase
{
    public function testProcessWithoutAlias()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
        ;
        $fooExtendedDefinition = $container
            ->register('foo.extended')
            ->setPublic(true)
            ->setDecoratedService('foo')
        ;
        $barDefinition = $container
            ->register('bar')
            ->setPublic(true)
        ;
        $barExtendedDefinition = $container
            ->register('bar.extended')
            ->setPublic(true)
            ->setDecoratedService('bar', 'bar.yoo')
        ;

        $this->process($container);

        self::assertEquals('foo.extended', $container->getAlias('foo'));
        self::assertFalse($container->getAlias('foo')->isPublic());

        self::assertEquals('bar.extended', $container->getAlias('bar'));
        self::assertTrue($container->getAlias('bar')->isPublic());

        self::assertSame($fooDefinition, $container->getDefinition('foo.extended.inner'));
        self::assertFalse($container->getDefinition('foo.extended.inner')->isPublic());

        self::assertSame($barDefinition, $container->getDefinition('bar.yoo'));
        self::assertFalse($container->getDefinition('bar.yoo')->isPublic());

        self::assertNull($fooExtendedDefinition->getDecoratedService());
        self::assertNull($barExtendedDefinition->getDecoratedService());
    }

    public function testProcessWithAlias()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(true)
        ;
        $container->setAlias('foo.alias', new Alias('foo', false));
        $fooExtendedDefinition = $container
            ->register('foo.extended')
            ->setPublic(true)
            ->setDecoratedService('foo.alias')
        ;

        $this->process($container);

        self::assertEquals('foo.extended', $container->getAlias('foo.alias'));
        self::assertFalse($container->getAlias('foo.alias')->isPublic());

        self::assertEquals('foo', $container->getAlias('foo.extended.inner'));
        self::assertFalse($container->getAlias('foo.extended.inner')->isPublic());

        self::assertNull($fooExtendedDefinition->getDecoratedService());
    }

    public function testProcessWithPriority()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
        ;
        $barDefinition = $container
            ->register('bar')
            ->setPublic(true)
            ->setDecoratedService('foo')
        ;
        $bazDefinition = $container
            ->register('baz')
            ->setPublic(true)
            ->setDecoratedService('foo', null, 5)
        ;
        $quxDefinition = $container
            ->register('qux')
            ->setPublic(true)
            ->setDecoratedService('foo', null, 3)
        ;

        $this->process($container);

        self::assertEquals('bar', $container->getAlias('foo'));
        self::assertFalse($container->getAlias('foo')->isPublic());

        self::assertSame($fooDefinition, $container->getDefinition('baz.inner'));
        self::assertFalse($container->getDefinition('baz.inner')->isPublic());

        self::assertEquals('qux', $container->getAlias('bar.inner'));
        self::assertFalse($container->getAlias('bar.inner')->isPublic());

        self::assertEquals('baz', $container->getAlias('qux.inner'));
        self::assertFalse($container->getAlias('qux.inner')->isPublic());

        self::assertNull($barDefinition->getDecoratedService());
        self::assertNull($bazDefinition->getDecoratedService());
        self::assertNull($quxDefinition->getDecoratedService());
    }

    public function testProcessWithInvalidDecorated()
    {
        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        self::assertFalse($container->has('decorator'));

        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        self::assertTrue($container->has('decorator'));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $decoratorDefinition->decorationOnInvalid);

        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_service')
        ;

        self::expectException(ServiceNotFoundException::class);
        $this->process($container);
    }

    public function testProcessNoInnerAliasWithInvalidDecorated()
    {
        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        self::assertFalse($container->hasAlias('decorator.inner'));
    }

    public function testProcessWithInvalidDecoratedAndWrongBehavior()
    {
        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, 12)
        ;

        self::expectException(ServiceNotFoundException::class);
        $this->process($container);
    }

    public function testProcessMovesTagsFromDecoratedDefinitionToDecoratingDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        self::assertEmpty($container->getDefinition('baz.inner')->getTags());
        self::assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar']], $container->getDefinition('baz')->getTags());
    }

    public function testProcessMovesTagsFromDecoratedDefinitionToDecoratingDefinitionMultipleTimes()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(true)
            ->setTags(['bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('deco1')
            ->setDecoratedService('foo', null, 50)
        ;
        $container
            ->register('deco2')
            ->setDecoratedService('foo', null, 2)
        ;

        $this->process($container);

        self::assertEmpty($container->getDefinition('deco1')->getTags());
        self::assertEquals(['bar' => ['attr' => 'baz']], $container->getDefinition('deco2')->getTags());
    }

    public function testProcessLeavesServiceLocatorTagOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['container.service_locator' => [0 => []], 'bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        self::assertEquals(['container.service_locator' => [0 => []]], $container->getDefinition('baz.inner')->getTags());
        self::assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar']], $container->getDefinition('baz')->getTags());
    }

    public function testProcessLeavesServiceSubscriberTagOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['container.service_subscriber' => [], 'container.service_subscriber.locator' => [], 'bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        self::assertEquals(['container.service_subscriber' => [], 'container.service_subscriber.locator' => []], $container->getDefinition('baz.inner')->getTags());
        self::assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar']], $container->getDefinition('baz')->getTags());
    }

    public function testCannotDecorateSyntheticService()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setSynthetic(true)
        ;
        $container
            ->register('baz')
            ->setDecoratedService('foo')
        ;

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('A synthetic service cannot be decorated: service "baz" cannot decorate "foo".');
        $this->process($container);
    }

    public function testGenericInnerReference()
    {
        $container = new ContainerBuilder();
        $container->register('foo');

        $container->register('bar')
            ->setDecoratedService('foo')
            ->setProperty('prop', new Reference('.inner'));

        $this->process($container);

        self::assertEquals(['prop' => new Reference('bar.inner')], $container->getDefinition('bar')->getProperties());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new DecoratorServicePass();
        $pass->process($container);
    }
}
