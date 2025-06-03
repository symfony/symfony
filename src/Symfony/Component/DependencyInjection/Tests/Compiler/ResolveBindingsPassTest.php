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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\DefinitionErrorExceptionPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedEnumArgumentDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedIterableArgumentDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WithTarget;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class ResolveBindingsPassTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testProcess()
    {
        $container = new ContainerBuilder();

        $bindings = [
            CaseSensitiveClass::class => new BoundArgument(new Reference('foo')),
            'Psr\Container\ContainerInterface $container' => new BoundArgument(new ServiceLocatorArgument([]), true, BoundArgument::INSTANCEOF_BINDING),
            'iterable $objects' => new BoundArgument(new TaggedIteratorArgument('tag.name'), true, BoundArgument::INSTANCEOF_BINDING),
        ];

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments([1 => '123']);
        $definition->addMethodCall('setSensitiveClass');
        $definition->setBindings($bindings);

        $container->register('foo', CaseSensitiveClass::class)
            ->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $expected = [
            0 => new Reference('foo'),
            1 => '123',
            3 => new ServiceLocatorArgument([]),
            4 => new TaggedIteratorArgument('tag.name'),
        ];
        $this->assertEquals($expected, $definition->getArguments());
        $this->assertEquals([['setSensitiveClass', [new Reference('foo')]]], $definition->getMethodCalls());
    }

    public function testProcessEnum()
    {
        $container = new ContainerBuilder();

        $bindings = [
            FooUnitEnum::class.' $bar' => new BoundArgument(FooUnitEnum::BAR),
        ];

        $definition = $container->register(NamedEnumArgumentDummy::class, NamedEnumArgumentDummy::class);
        $definition->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $expected = [FooUnitEnum::BAR];
        $this->assertEquals($expected, $definition->getArguments());
    }

    public function testUnusedBinding()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A binding is configured for an argument named "$quz" for service "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy", but no corresponding argument has been found. It may be unused and should be removed, or it may have a typo.');
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setBindings(['$quz' => '123']);

        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }

    public function testMissingParent()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A binding is configured for an argument named "$quz" for service "Symfony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists", but no corresponding argument has been found. It may be unused and should be removed, or it may have a typo.');

        $container = new ContainerBuilder();

        $definition = $container->register(ParentNotExists::class, ParentNotExists::class);
        $definition->setBindings(['$quz' => '123']);

        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }

    public function testTypedReferenceSupport()
    {
        $container = new ContainerBuilder();

        $bindings = [
            CaseSensitiveClass::class => new BoundArgument(new Reference('foo')),
            CaseSensitiveClass::class.' $c' => new BoundArgument(new Reference('bar')),
        ];

        // Explicit service id
        $definition1 = $container->register('def1', NamedArgumentsDummy::class);
        $definition1->addArgument($typedRef = new TypedReference('bar', CaseSensitiveClass::class));
        $definition1->setBindings($bindings);

        $definition2 = $container->register('def2', NamedArgumentsDummy::class);
        $definition2->addArgument(new TypedReference(CaseSensitiveClass::class, CaseSensitiveClass::class));
        $definition2->setBindings($bindings);

        $definition3 = $container->register('def3', NamedArgumentsDummy::class);
        $definition3->addArgument(new TypedReference(CaseSensitiveClass::class, CaseSensitiveClass::class, ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE, 'c'));
        $definition3->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $this->assertEquals([$typedRef], $container->getDefinition('def1')->getArguments());
        $this->assertEquals([new Reference('foo')], $container->getDefinition('def2')->getArguments());
        $this->assertEquals([new Reference('bar')], $container->getDefinition('def3')->getArguments());
    }

    /**
     * @group legacy
     */
    public function testScalarSetterAnnotation()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Relying on the "@required" annotation on method "Symfony\Component\DependencyInjection\Tests\Compiler\ScalarSetterAnnotation::setDefaultLocale()" is deprecated, use the "Symfony\Contracts\Service\Attribute\Required" attribute instead.');

        $container = new ContainerBuilder();

        $definition = $container->autowire('foo', ScalarSetterAnnotation::class);
        $definition->setBindings(['$defaultLocale' => 'fr']);

        (new AutowireRequiredMethodsPass())->process($container);
        (new ResolveBindingsPass())->process($container);

        $this->assertEquals([['setDefaultLocale', ['fr']]], $definition->getMethodCalls());
    }

    public function testScalarSetterAttribute()
    {
        $container = new ContainerBuilder();

        $definition = $container->autowire('foo', ScalarSetter::class);
        $definition->setBindings(['$defaultLocale' => 'fr']);

        (new AutowireRequiredMethodsPass())->process($container);
        (new ResolveBindingsPass())->process($container);

        $this->assertEquals([['setDefaultLocale', ['fr']]], $definition->getMethodCalls());
    }

    public function testWithNonExistingSetterAndBinding()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid service "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy": method "setLogger()" does not exist.');
        $container = new ContainerBuilder();

        $bindings = [
            '$c' => (new Definition('logger'))->setFactory('logger'),
        ];

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->addMethodCall('setLogger');
        $definition->setBindings($bindings);

        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }

    public function testSyntheticServiceWithBind()
    {
        $container = new ContainerBuilder();
        $argument = new BoundArgument('bar');

        $container->register('foo', 'stdClass')
            ->addArgument(new Reference('synthetic.service'));

        $container->register('synthetic.service')
            ->setSynthetic(true)
            ->setBindings(['$apiKey' => $argument]);

        $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class)
            ->setBindings(['$apiKey' => $argument]);

        (new ResolveBindingsPass())->process($container);
        (new DefinitionErrorExceptionPass())->process($container);

        $this->assertSame([1 => 'bar'], $container->getDefinition(NamedArgumentsDummy::class)->getArguments());
    }

    public function testEmptyBindingTypehint()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Did you forget to add the type "string" to argument "$apiKey" of method "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy::__construct()"?');

        $container = new ContainerBuilder();
        $bindings = [
            'string $apiKey' => new BoundArgument('foo'),
        ];
        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setBindings($bindings);
        $pass = new ResolveBindingsPass();
        $pass->process($container);
    }

    public function testIterableBindingTypehint()
    {
        $autoloader = static function ($class) {
            if ('iterable' === $class) {
                throw new \RuntimeException('We should not search pseudo-type iterable as class');
            }
        };
        spl_autoload_register($autoloader);

        $container = new ContainerBuilder();
        $definition = $container->register('bar', NamedIterableArgumentDummy::class);
        $definition->setBindings([
            'iterable $items' => new TaggedIteratorArgument('foo'),
        ]);
        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $this->assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition('bar')->getArgument(0));

        spl_autoload_unregister($autoloader);
    }

    public function testBindWithTarget()
    {
        $container = new ContainerBuilder();

        $container->register('with_target', WithTarget::class)
            ->setBindings([BarInterface::class.' $imageStorage' => new Reference('bar')]);

        (new ResolveBindingsPass())->process($container);

        $this->assertSame('bar', (string) $container->getDefinition('with_target')->getArgument(0));
    }

    public function testBindWithNamedArgs()
    {
        $container = new ContainerBuilder();

        $bindings = [
            '$apiKey' => new BoundArgument('K'),
        ];

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(['c' => 'C', 'hostName' => 'H']);
        $definition->setBindings($bindings);

        $container->register('foo', CaseSensitiveClass::class);

        $pass = new ResolveBindingsPass();
        $pass->process($container);

        $this->assertEquals(['C', 'K', 'H'], $definition->getArguments());
    }
}
