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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractDescriptorTestCase extends TestCase
{
    private $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS=121');
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    /** @dataProvider getDescribeRouteCollectionTestData */
    public function testDescribeRouteCollection(RouteCollection $routes, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $routes);
    }

    public static function getDescribeRouteCollectionTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getRouteCollections());
    }

    /** @dataProvider getDescribeRouteTestData */
    public function testDescribeRoute(Route $route, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $route);
    }

    public static function getDescribeRouteTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getRoutes());
    }

    /** @dataProvider getDescribeContainerParametersTestData */
    public function testDescribeContainerParameters(ParameterBag $parameters, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $parameters);
    }

    public static function getDescribeContainerParametersTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getContainerParameters());
    }

    /** @dataProvider getDescribeContainerBuilderTestData */
    public function testDescribeContainerBuilder(ContainerBuilder $builder, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $builder, $options);
    }

    public static function getDescribeContainerBuilderTestData(): array
    {
        return static::getContainerBuilderDescriptionTestData(ObjectsProvider::getContainerBuilders());
    }

    /**
     * @dataProvider getDescribeContainerExistingClassDefinitionTestData
     */
    public function testDescribeContainerExistingClassDefinition(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }

    public static function getDescribeContainerExistingClassDefinitionTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getContainerDefinitionsWithExistingClasses());
    }

    /** @dataProvider getDescribeContainerDefinitionTestData */
    public function testDescribeContainerDefinition(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }

    public static function getDescribeContainerDefinitionTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getContainerDefinitions());
    }

    /** @dataProvider getDescribeContainerDefinitionWithArgumentsShownTestData */
    public function testDescribeContainerDefinitionWithArgumentsShown(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition, ['show_arguments' => true]);
    }

    public static function getDescribeContainerDefinitionWithArgumentsShownTestData(): array
    {
        $definitions = ObjectsProvider::getContainerDefinitions();
        $definitionsWithArgs = [];

        foreach ($definitions as $key => $definition) {
            $definitionsWithArgs[str_replace('definition_', 'definition_arguments_', $key)] = $definition;
        }

        $definitionsWithArgs['definition_arguments_with_enum'] = (new Definition('definition_with_enum'))->setArgument(0, FooUnitEnum::FOO);

        return static::getDescriptionTestData($definitionsWithArgs);
    }

    /** @dataProvider getDescribeContainerAliasTestData */
    public function testDescribeContainerAlias(Alias $alias, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $alias);
    }

    public static function getDescribeContainerAliasTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getContainerAliases());
    }

    /** @dataProvider getDescribeContainerDefinitionWhichIsAnAliasTestData */
    public function testDescribeContainerDefinitionWhichIsAnAlias(Alias $alias, $expectedDescription, ContainerBuilder $builder, $options = [])
    {
        $this->assertDescription($expectedDescription, $builder, $options);
    }

    public static function getDescribeContainerDefinitionWhichIsAnAliasTestData(): array
    {
        $builder = current(ObjectsProvider::getContainerBuilders());
        $builder->setDefinition('service_1', $builder->getDefinition('definition_1'));
        $builder->setDefinition('.service_2', $builder->getDefinition('.definition_2'));

        $aliases = ObjectsProvider::getContainerAliases();
        $aliasesWithDefinitions = [];
        foreach ($aliases as $name => $alias) {
            $aliasesWithDefinitions[str_replace('alias_', 'alias_with_definition_', $name)] = $alias;
        }

        $i = 0;
        $data = static::getDescriptionTestData($aliasesWithDefinitions);
        foreach ($aliases as $name => $alias) {
            $file = array_pop($data[$i]);
            $data[$i][] = $builder;
            $data[$i][] = ['id' => $name];
            $data[$i][] = $file;
            ++$i;
        }

        return $data;
    }

    /** @dataProvider getDescribeContainerParameterTestData */
    public function testDescribeContainerParameter($parameter, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $parameter, $options);
    }

    public static function getDescribeContainerParameterTestData(): array
    {
        $data = static::getDescriptionTestData(ObjectsProvider::getContainerParameter());

        $file = array_pop($data[0]);
        $data[0][] = ['parameter' => 'database_name'];
        $data[0][] = $file;
        $file = array_pop($data[1]);
        $data[1][] = ['parameter' => 'twig.form.resources'];
        $data[1][] = $file;

        return $data;
    }

    /** @dataProvider getDescribeEventDispatcherTestData */
    public function testDescribeEventDispatcher(EventDispatcher $eventDispatcher, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $eventDispatcher, $options);
    }

    public static function getDescribeEventDispatcherTestData(): array
    {
        return static::getEventDispatcherDescriptionTestData(ObjectsProvider::getEventDispatchers());
    }

    /** @dataProvider getDescribeCallableTestData */
    public function testDescribeCallable($callable, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $callable);
    }

    public static function getDescribeCallableTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getCallables());
    }

    /**
     * @group legacy
     *
     * @dataProvider getDescribeDeprecatedCallableTestData
     */
    public function testDescribeDeprecatedCallable($callable, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $callable);
    }

    public static function getDescribeDeprecatedCallableTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getDeprecatedCallables());
    }

    /** @dataProvider getClassDescriptionTestData */
    public function testGetClassDescription($object, $expectedDescription)
    {
        $this->assertEquals($expectedDescription, $this->getDescriptor()->getClassDescription($object));
    }

    public static function getClassDescriptionTestData(): array
    {
        return [
            [ClassWithDocCommentOnMultipleLines::class, 'This is the first line of the description. This is the second line.'],
            [ClassWithDocCommentWithoutInitialSpace::class, 'Foo.'],
            [ClassWithoutDocComment::class, ''],
            [ClassWithDocComment::class, 'This is a class with a doc comment.'],
        ];
    }

    /**
     * @dataProvider getDeprecationsTestData
     */
    public function testGetDeprecations(ContainerBuilder $builder, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $builder, ['deprecations' => true]);
    }

    public static function getDeprecationsTestData(): array
    {
        return static::getDescriptionTestData(ObjectsProvider::getContainerDeprecations());
    }

    abstract protected static function getDescriptor();

    abstract protected static function getFormat();

    private function assertDescription($expectedDescription, $describedObject, array $options = [])
    {
        $options['is_debug'] = false;
        $options['raw_output'] = true;
        $options['raw_text'] = true;
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

        if ('txt' === $this->getFormat()) {
            $options['output'] = new SymfonyStyle(new ArrayInput([]), $output);
        }

        $this->getDescriptor()->describe($output, $describedObject, $options);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_encode(json_decode($expectedDescription), \JSON_PRETTY_PRINT), json_encode(json_decode($output->fetch()), \JSON_PRETTY_PRINT));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(\PHP_EOL, "\n", $output->fetch())));
        }
    }

    private static function getDescriptionTestData(iterable $objects): array
    {
        $data = [];
        foreach ($objects as $name => $object) {
            $file = sprintf('%s.%s', trim($name, '.'), static::getFormat());
            $description = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
            $data[] = [$object, $description, $file];
        }

        return $data;
    }

    private static function getContainerBuilderDescriptionTestData(array $objects): array
    {
        $variations = [
            'services' => ['show_hidden' => true],
            'public' => ['show_hidden' => false],
            'tag1' => ['show_hidden' => true, 'tag' => 'tag1'],
            'tags' => ['group_by' => 'tags', 'show_hidden' => true],
            'arguments' => ['show_hidden' => false, 'show_arguments' => true],
        ];

        $data = [];
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $file = sprintf('%s_%s.%s', trim($name, '.'), $suffix, static::getFormat());
                $description = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
                $data[] = [$object, $description, $options, $file];
            }
        }

        return $data;
    }

    private static function getEventDispatcherDescriptionTestData(array $objects): array
    {
        $variations = [
            'events' => [],
            'event1' => ['event' => 'event1'],
        ];

        $data = [];
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $file = sprintf('%s_%s.%s', trim($name, '.'), $suffix, static::getFormat());
                $description = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
                $data[] = [$object, $description, $options, $file];
            }
        }

        return $data;
    }

    /** @dataProvider getDescribeContainerBuilderWithPriorityTagsTestData */
    public function testDescribeContainerBuilderWithPriorityTags(ContainerBuilder $builder, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $builder, $options);
    }

    public static function getDescribeContainerBuilderWithPriorityTagsTestData(): array
    {
        $variations = ['priority_tag' => ['tag' => 'tag1']];
        $data = [];
        foreach (ObjectsProvider::getContainerBuildersWithPriorityTags() as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $file = sprintf('%s_%s.%s', trim($name, '.'), $suffix, static::getFormat());
                $description = file_get_contents(__DIR__.'/../../Fixtures/Descriptor/'.$file);
                $data[] = [$object, $description, $options];
            }
        }

        return $data;
    }
}
