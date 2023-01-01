<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FooUnitEnum;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Suit;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\CompiledRoute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectsProvider
{
    public static function getRouteCollections()
    {
        $collection1 = new RouteCollection();
        foreach (self::getRoutes() as $name => $route) {
            $collection1->add($name, $route);
        }

        return ['route_collection_1' => $collection1];
    }

    public static function getRoutes()
    {
        return [
            'route_1' => new RouteStub(
                '/hello/{name}',
                ['name' => 'Joseph'],
                ['name' => '[a-z]+'],
                ['opt1' => 'val1', 'opt2' => 'val2'],
                'localhost',
                ['http', 'https'],
                ['get', 'head']
            ),
            'route_2' => new RouteStub(
                '/name/add',
                [],
                [],
                ['opt1' => 'val1', 'opt2' => 'val2'],
                'localhost',
                ['http', 'https'],
                ['put', 'post'],
                "context.getMethod() in ['GET', 'HEAD', 'POST']"
            ),
        ];
    }

    public static function getContainerParameters()
    {
        yield 'parameters_1' => new ParameterBag([
            'integer' => 12,
            'string' => 'Hello world!',
            'boolean' => true,
            'array' => [12, 'Hello world!', true],
        ]);

        yield 'parameters_enums' => new ParameterBag([
            'unit_enum' => FooUnitEnum::BAR,
            'backed_enum' => Suit::Hearts,
            'array_of_enums' => Suit::cases(),
            'map' => [
                'mixed' => [Suit::Hearts, FooUnitEnum::BAR],
                'single' => FooUnitEnum::BAR,
            ],
        ]);
    }

    public static function getContainerParameter()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('database_name', 'symfony');
        $builder->setParameter('twig.form.resources', [
            'bootstrap_3_horizontal_layout.html.twig',
            'bootstrap_3_layout.html.twig',
            'form_div_layout.html.twig',
            'form_table_layout.html.twig',
        ]);

        return [
            'parameter' => $builder,
            'array_parameter' => $builder,
        ];
    }

    public static function getContainerDeprecations()
    {
        $builderWithDeprecations = new ContainerBuilder();
        $builderWithDeprecations->setParameter('kernel.cache_dir', __DIR__.'/../../Fixtures/Descriptor/cache');
        $builderWithDeprecations->setParameter('kernel.build_dir', __DIR__.'/../../Fixtures/Descriptor/cache');
        $builderWithDeprecations->setParameter('kernel.container_class', 'KernelContainerWith');

        $builderWithoutDeprecations = new ContainerBuilder();
        $builderWithoutDeprecations->setParameter('kernel.cache_dir', __DIR__.'/../../Fixtures/Descriptor/cache');
        $builderWithoutDeprecations->setParameter('kernel.build_dir', __DIR__.'/../../Fixtures/Descriptor/cache');
        $builderWithoutDeprecations->setParameter('kernel.container_class', 'KernelContainerWithout');

        return [
            'deprecations' => $builderWithDeprecations,
            'deprecations_empty' => $builderWithoutDeprecations,
        ];
    }

    public static function getContainerBuilders()
    {
        $builder1 = new ContainerBuilder();
        $builder1->setDefinitions(self::getContainerDefinitions());
        $builder1->setAliases(self::getContainerAliases());

        return ['builder_1' => $builder1];
    }

    public static function getContainerDefinitionsWithExistingClasses()
    {
        return [
            'existing_class_def_1' => new Definition(ClassWithDocComment::class),
            'existing_class_def_2' => new Definition(ClassWithoutDocComment::class),
        ];
    }

    public static function getContainerDefinitions()
    {
        $definition1 = new Definition('Full\\Qualified\\Class1');
        $definition2 = new Definition('Full\\Qualified\\Class2');
        $definition3 = new Definition('Full\\Qualified\\Class3');

        return [
            'definition_1' => $definition1
                ->setPublic(true)
                ->setSynthetic(false)
                ->setLazy(true)
                ->setAbstract(true)
                ->addArgument(new Reference('.definition_2'))
                ->addArgument('%parameter%')
                ->addArgument(new Definition('inline_service', ['arg1', 'arg2']))
                ->addArgument([
                    'foo',
                    new Reference('.definition_2'),
                    new Definition('inline_service'),
                ])
                ->addArgument(new IteratorArgument([
                    new Reference('definition_1'),
                    new Reference('.definition_2'),
                ]))
                ->addArgument(new AbstractArgument('placeholder'))
                ->setFactory(['Full\\Qualified\\FactoryClass', 'get']),
            '.definition_2' => $definition2
                ->setPublic(false)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setAbstract(false)
                ->addTag('tag1', ['attr1' => 'val1', 'attr2' => 'val2'])
                ->addTag('tag1', ['attr3' => 'val3'])
                ->addTag('tag2')
                ->addMethodCall('setMailer', [new Reference('mailer')])
                ->setFactory([new Reference('factory.service'), 'get']),
            '.definition_3' => $definition3
                ->setFile('/path/to/file')
                ->setFactory([new Definition('Full\\Qualified\\FactoryClass'), 'get']),
            'definition_without_class' => new Definition(),
        ];
    }

    public static function getContainerBuildersWithPriorityTags()
    {
        $builder = new ContainerBuilder();
        $builder->setDefinitions(self::getContainerDefinitionsWithPriorityTags());

        return ['builder' => $builder];
    }

    public static function getContainerDefinitionsWithPriorityTags()
    {
        $definition1 = new Definition('Full\\Qualified\\Class1');
        $definition2 = new Definition('Full\\Qualified\\Class2');
        $definition3 = new Definition('Full\\Qualified\\Class3');
        $definition4 = new Definition('Full\\Qualified\\Class4');

        return [
            'definition_1' => $definition1
                ->setPublic(true)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setAbstract(false)
                ->addTag('tag1', ['attr1' => 'val1', 'priority' => 30])
                ->addTag('tag1', ['attr2' => 'val2'])
                ->addTag('tag2')
                ->addMethodCall('setMailer', [new Reference('mailer')])
                ->setFactory([new Reference('factory.service'), 'get']),
            'definition_2' => $definition2
                ->setPublic(true)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setAbstract(false)
                ->addTag('tag1', ['attr1' => 'val1', 'attr2' => 'val2', 'priority' => -20]),
            'definition_3' => $definition3
                ->setPublic(true)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setAbstract(false)
                ->addTag('tag1', ['attr1' => 'val1', 'attr2' => 'val2', 'priority' => 0])
                ->addTag('tag1', ['attr3' => 'val3', 'priority' => 40]),
            'definition_4' => $definition4
                ->setPublic(true)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setAbstract(false)
                ->addTag('tag1', ['priority' => 0]),
        ];
    }

    public static function getContainerAliases()
    {
        return [
            'alias_1' => new Alias('service_1', true),
            '.alias_2' => new Alias('.service_2', false),
        ];
    }

    public static function getEventDispatchers()
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addListener('event1', 'var_dump', 255);
        $eventDispatcher->addListener('event1', fn () => 'Closure', -1);
        $eventDispatcher->addListener('event2', new CallableClass());

        return ['event_dispatcher_1' => $eventDispatcher];
    }

    public static function getCallables(): array
    {
        return [
            'callable_1' => 'array_key_exists',
            'callable_2' => ['Symfony\\Bundle\\FrameworkBundle\\Tests\\Console\\Descriptor\\CallableClass', 'staticMethod'],
            'callable_3' => [new CallableClass(), 'method'],
            'callable_4' => 'Symfony\\Bundle\\FrameworkBundle\\Tests\\Console\\Descriptor\\CallableClass::staticMethod',
            'callable_6' => fn () => 'Closure',
            'callable_7' => new CallableClass(),
            'callable_from_callable' => (new CallableClass())(...),
        ];
    }

    public static function getDeprecatedCallables(): array
    {
        return [
            'callable_5' => ['Symfony\\Bundle\\FrameworkBundle\\Tests\\Console\\Descriptor\\ExtendedCallableClass', 'parent::staticMethod'],
        ];
    }
}

class CallableClass
{
    public function __invoke()
    {
    }

    public static function staticMethod()
    {
    }

    public function method()
    {
    }
}

class ExtendedCallableClass extends CallableClass
{
    public static function staticMethod()
    {
    }
}

class RouteStub extends Route
{
    public function compile(): CompiledRoute
    {
        return new CompiledRoute('', '#PATH_REGEX#', [], [], '#HOST_REGEX#');
    }
}

class ClassWithoutDocComment
{
}

/**
 * This is a class with a doc comment.
 */
class ClassWithDocComment
{
}

/**
 * This is the first line of the description.
 * This is the second line.
 *
 * This is the third and shouldn't be shown.
 *
 * @annot should not be parsed
 */
class ClassWithDocCommentOnMultipleLines
{
}

/**
 *Foo.
 *
 * @annot should not be parsed
 */
class ClassWithDocCommentWithoutInitialSpace
{
}
