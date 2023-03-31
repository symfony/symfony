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
        $values = $argument->getValues();
        --$values[1];
        $argument->setValues($values);

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
}
