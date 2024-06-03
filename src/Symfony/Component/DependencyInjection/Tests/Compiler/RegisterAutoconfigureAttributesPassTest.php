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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureAttributed;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfiguredInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeated;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedBindings;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedCalls;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedOverwrite;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedProperties;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AutoconfigureRepeatedTag;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists;

/**
 * @requires PHP 8
 */
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
}
