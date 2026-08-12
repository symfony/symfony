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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\RegisterAutoconfigureAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureAttributed;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfiguredInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeated;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedBindings;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedCalls;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedOverwrite;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedProperties;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedTag;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureResourceTagsAttributed;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureWithExpressionFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureWithInstanceExternalFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureWithInvokableFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureWithStaticExternalFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureWithStaticSelfFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LazyAutoconfigured;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LazyLoaded;
use Symfony\Component\DependencyInjection\Tests\Fixtures\MultipleAutoconfigureAttributed;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists;
use Symfony\Component\DependencyInjection\Tests\Fixtures\StaticConstructorAutoconfigure;

class RegisterAutoconfigureAttributesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureAttributed::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $argument = new BoundArgument(1, false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureAttributed.php'));

        $expected = (new ChildDefinition(''))
            ->setLazy(true)
            ->setPublic(true)
            ->setAutowired(true)
            ->setShared(true)
            ->setProperties(['bar' => 'baz'])
            ->setConfigurator(new Reference('bla'))
            ->addTag('a_tag')
            ->addTag('another_tag', ['attr' => 234])
            ->addMethodCall('setBar', [2, 3])
            ->setBindings(['$bar' => $argument])
            ->setFactory([null, 'create'])
        ;
        $this->assertEquals([AutoconfigureAttributed::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testIgnoreAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureAttributed::class)
            ->addTag('container.ignore_attributes')
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $this->assertSame([], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredTag()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfiguredInterface::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->addTag(AutoconfiguredInterface::class, ['foo' => 123])
        ;
        $this->assertEquals([AutoconfiguredInterface::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeated()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeated::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setLazy(true)
            ->setPublic(true)
            ->setShared(false);

        $this->assertEquals([AutoconfigureRepeated::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeatedOverwrite()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeatedOverwrite::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setLazy(true)
            ->setPublic(false)
            ->setShared(true);

        $this->assertEquals([AutoconfigureRepeatedOverwrite::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeatedTag()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeatedTag::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->addTag('foo', ['priority' => 2])
            ->addTag('bar');

        $this->assertEquals([AutoconfigureRepeatedTag::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeatedCalls()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeatedCalls::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->addMethodCall('setBar', ['arg2'])
            ->addMethodCall('setFoo', ['arg1']);

        $this->assertEquals([AutoconfigureRepeatedCalls::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeatedBindingsOverwrite()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeatedBindings::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setBindings(['$arg' => new BoundArgument('bar', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureRepeatedBindings.php'))]);

        $this->assertEquals([AutoconfigureRepeatedBindings::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredRepeatedPropertiesOverwrite()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureRepeatedProperties::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setProperties([
                '$foo' => 'bar',
                '$bar' => 'baz',
            ]);

        $this->assertEquals([AutoconfigureRepeatedProperties::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testMissingParent()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(ParentNotExists::class, ParentNotExists::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testStaticConstructor()
    {
        $container = new ContainerBuilder();
        $container->register('foo', StaticConstructorAutoconfigure::class)
            ->setAutoconfigured(true);

        $argument = new BoundArgument('foo', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/StaticConstructorAutoconfigure.php'));

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory([null, 'create'])
            ->setBindings(['$foo' => $argument])
        ;
        $this->assertEquals([StaticConstructorAutoconfigure::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfigureWithStaticSelfFactory()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureWithStaticSelfFactory::class)
            ->setAutoconfigured(true);

        $argument = new BoundArgument('foo', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureWithStaticSelfFactory.php'));

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory([null, 'create'])
            ->setBindings(['$foo' => $argument])
        ;
        $this->assertEquals([AutoconfigureWithStaticSelfFactory::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfigureWithStaticExternalFactory()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureWithStaticExternalFactory::class)
            ->setAutoconfigured(true);

        $argument = new BoundArgument('foo', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureWithStaticExternalFactory.php'));

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory([FactoryDummy::class, 'create'])
            ->setBindings(['$foo' => $argument])
        ;
        $this->assertEquals([AutoconfigureWithStaticExternalFactory::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfigureWithInstanceExternalFactory()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureWithInstanceExternalFactory::class)
            ->setAutoconfigured(true);

        $argument = new BoundArgument('foo', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureWithInstanceExternalFactory.php'));

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory([new Reference('factory_for_autoconfigure'), 'createStatic'])
            ->setBindings(['$foo' => $argument])
        ;
        $this->assertEquals([AutoconfigureWithInstanceExternalFactory::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfigureWithInvokableFactory()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureWithInvokableFactory::class)
            ->setAutoconfigured(true);

        $argument = new BoundArgument('foo', false, BoundArgument::INSTANCEOF_BINDING, realpath(__DIR__.'/../Fixtures/AutoconfigureWithInvokableFactory.php'));

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory([new Reference('factory_for_autoconfigure'), '__invoke'])
            ->setBindings(['$foo' => $argument])
        ;
        $this->assertEquals([AutoconfigureWithInvokableFactory::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfigureWithExpressionFactory()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureWithExpressionFactory::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setFactory('@=service("factory_for_autoconfigure").create()')
        ;
        $this->assertEquals([AutoconfigureWithExpressionFactory::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testLazyServiceAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('foo', LazyLoaded::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->setLazy(true)
        ;
        $this->assertEquals([LazyLoaded::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testLazyNotCompatibleWithAutoconfigureAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('foo', LazyAutoconfigured::class)
            ->setAutoconfigured(true);

        try {
            (new RegisterAutoconfigureAttributesPass())->process($container);
        } catch (AutoconfigureFailedException $e) {
            $this->assertSame('Using both attributes #[Lazy] and #[Autoconfigure] on an argument is not allowed; use the "lazy" parameter of #[Autoconfigure] instead.', $e->getMessage());
        }
    }

    public function testMultipleAutoconfigureAllowed()
    {
        $container = new ContainerBuilder();
        $container->register('foo', MultipleAutoconfigureAttributed::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->addTag('foo')
            ->addTag('bar')
        ;
        $this->assertEquals([MultipleAutoconfigureAttributed::class => $expected], $container->getAutoconfiguredInstanceof());
    }

    public function testAutoconfiguredResourceTags()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AutoconfigureResourceTagsAttributed::class)
            ->setAutoconfigured(true);

        (new RegisterAutoconfigureAttributesPass())->process($container);

        $expected = (new ChildDefinition(''))
            ->addResourceTag('my.tag', ['foo' => 'bar'])
            ->addResourceTag('another.tag')
        ;

        $this->assertEquals([AutoconfigureResourceTagsAttributed::class => $expected], $container->getAutoconfiguredInstanceof());
    }
}
