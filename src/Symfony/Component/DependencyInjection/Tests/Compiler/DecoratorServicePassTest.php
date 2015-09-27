<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;

class DecoratorServicePassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessWithoutAlias()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
            ->setPublic(false)
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

        $this->assertEquals('foo.extended', $container->getAlias('foo'));
        $this->assertFalse($container->getAlias('foo')->isPublic());

        $this->assertEquals('bar.extended', $container->getAlias('bar'));
        $this->assertTrue($container->getAlias('bar')->isPublic());

        $this->assertSame($fooDefinition, $container->getDefinition('foo.extended.inner'));
        $this->assertFalse($container->getDefinition('foo.extended.inner')->isPublic());

        $this->assertSame($barDefinition, $container->getDefinition('bar.yoo'));
        $this->assertFalse($container->getDefinition('bar.yoo')->isPublic());

        $this->assertNull($fooExtendedDefinition->getDecoratedService());
        $this->assertNull($barExtendedDefinition->getDecoratedService());
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

        $this->assertEquals('foo.extended', $container->getAlias('foo.alias'));
        $this->assertFalse($container->getAlias('foo.alias')->isPublic());

        $this->assertEquals('foo', $container->getAlias('foo.extended.inner'));
        $this->assertFalse($container->getAlias('foo.extended.inner')->isPublic());

        $this->assertNull($fooExtendedDefinition->getDecoratedService());
    }

    public function testProcessWithPriority()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
            ->setPublic(false)
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

        $this->assertEquals('bar', $container->getAlias('foo'));
        $this->assertFalse($container->getAlias('foo')->isPublic());

        $this->assertSame($fooDefinition, $container->getDefinition('baz.inner'));
        $this->assertFalse($container->getDefinition('baz.inner')->isPublic());

        $this->assertEquals('qux', $container->getAlias('bar.inner'));
        $this->assertFalse($container->getAlias('bar.inner')->isPublic());

        $this->assertEquals('baz', $container->getAlias('qux.inner'));
        $this->assertFalse($container->getAlias('qux.inner')->isPublic());

        $this->assertNull($barDefinition->getDecoratedService());
        $this->assertNull($bazDefinition->getDecoratedService());
        $this->assertNull($quxDefinition->getDecoratedService());
    }

    protected function process(ContainerBuilder $container)
    {
        $repeatedPass = new DecoratorServicePass();
        $repeatedPass->process($container);
    }
}
