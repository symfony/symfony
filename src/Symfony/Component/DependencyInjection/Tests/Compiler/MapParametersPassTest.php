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
use Symfony\Component\DependencyInjection\Attribute\MapParameters;
use Symfony\Component\DependencyInjection\Compiler\MapParametersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class MapParametersPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.config', [
            'name' => 'myapp',
            'timeout' => 30,
        ]);

        $container->register(BasicParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $definition = $container->getDefinition(BasicParams::class);
        $this->assertEquals('myapp', $definition->getArgument('$name'));
        $this->assertEquals(30, $definition->getArgument('$timeout'));
    }

    public function testNestedObjects()
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.nested', [
            'name' => 'parent',
            'database' => ['host' => 'localhost', 'port' => 5432],
        ]);

        $container->register(NestedParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $definition = $container->getDefinition(NestedParams::class);
        $dbArg = $definition->getArgument('$database');
        $this->assertInstanceOf(Definition::class, $dbArg);
        $this->assertEquals(DatabaseParams::class, $dbArg->getClass());
        $this->assertEquals('localhost', $dbArg->getArgument('$host'));
    }

    public function testPublicProperty()
    {
        $container = new ContainerBuilder();
        $container->setParameter('props.config', ['name' => 'value']);

        $container->register(PropertyParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $definition = $container->getDefinition(PropertyParams::class);
        $this->assertEquals('value', $definition->getProperties()['name']);
    }

    public function testSetterMethod()
    {
        $container = new ContainerBuilder();
        $container->setParameter('setter.config', ['value' => 'test']);

        $container->register(SetterParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $methodCalls = $container->getDefinition(SetterParams::class)->getMethodCalls();
        $this->assertEquals('setValue', $methodCalls[0][0]);
    }

    public function testBackedEnum()
    {
        $container = new ContainerBuilder();
        $container->setParameter('enum.config', ['level' => 'error']);

        $container->register(EnumParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $levelArg = $container->getDefinition(EnumParams::class)->getArgument('$level');
        $this->assertInstanceOf(Definition::class, $levelArg);
        $this->assertEquals([LogLevel::class, 'from'], $levelArg->getFactory());
    }

    public function testDateTimeInterface()
    {
        $container = new ContainerBuilder();
        $container->setParameter('date.config', ['date' => '2023-01-01']);

        $container->register(DateTimeParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $dateArg = $container->getDefinition(DateTimeParams::class)->getArgument('$date');
        $this->assertEquals(\DateTimeImmutable::class, $dateArg->getClass());
    }

    public function testYearOnlyDateNormalization()
    {
        $container = new ContainerBuilder();
        $container->setParameter('year.config', ['date' => '2025']);

        $container->register(YearParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $dateArg = $container->getDefinition(YearParams::class)->getArgument('$date');
        $this->assertEquals(['2025-01-01'], $dateArg->getArguments());
    }

    public function testSnakeCaseNormalization()
    {
        $container = new ContainerBuilder();
        $container->setParameter('snake.config', ['database_host' => 'localhost']);

        $container->register(SnakeCaseParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $this->assertEquals('localhost', $container->getDefinition(SnakeCaseParams::class)->getArgument('$databaseHost'));
    }

    public function testFlatParameterMode()
    {
        $container = new ContainerBuilder();
        $container->setParameter('flat.name', 'myapp');
        $container->setParameter('flat.timeout', 30);

        $container->register(FlatParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $definition = $container->getDefinition(FlatParams::class);
        $this->assertEquals('myapp', $definition->getArgument('$name'));
        $this->assertEquals(30, $definition->getArgument('$timeout'));
    }

    public function testDefaultValue()
    {
        $container = new ContainerBuilder();
        $container->setParameter('default.config', ['required' => 'value']);

        $container->register(DefaultValueParams::class)->addTag('di.map_parameters');
        (new MapParametersPass())->process($container);

        $definition = $container->getDefinition(DefaultValueParams::class);
        $this->assertEquals('value', $definition->getArgument('$required'));
        $this->assertArrayNotHasKey('$optional', $definition->getArguments());
    }

    public function testMissingRequiredParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parameter "$required" is missing');

        $container = new ContainerBuilder();
        $container->setParameter('incomplete', ['name' => 'test']);
        $container->register(MissingRequiredParams::class)->addTag('di.map_parameters');

        (new MapParametersPass())->process($container);
    }

    public function testUnrecognizedKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not recognized');

        $container = new ContainerBuilder();
        $container->setParameter('extra', ['name' => 'ok', 'unknown' => 'bad']);
        $container->register(UnrecognizedParams::class)->addTag('di.map_parameters');

        (new MapParametersPass())->process($container);
    }

    public function testCircularReference()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $container = new ContainerBuilder();
        $container->setParameter('circular', ['child' => ['parent' => []]]);
        $container->register(CircularA::class)->addTag('di.map_parameters');

        (new MapParametersPass())->process($container);
    }
}

// Fixtures

#[MapParameters(path: 'app.config')]
class BasicParams
{
    public function __construct(public string $name, public int $timeout)
    {
    }
}

#[MapParameters(path: 'app.nested')]
class NestedParams
{
    public function __construct(public string $name, public DatabaseParams $database)
    {
    }
}

class DatabaseParams
{
    public function __construct(public string $host, public int $port)
    {
    }
}

#[MapParameters(path: 'props.config')]
class PropertyParams
{
    public string $name;
}

#[MapParameters(path: 'setter.config')]
class SetterParams
{
    private string $value;

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

enum LogLevel: string
{
    case DEBUG = 'debug';
    case ERROR = 'error';
}

#[MapParameters(path: 'enum.config')]
class EnumParams
{
    public function __construct(public LogLevel $level)
    {
    }
}

#[MapParameters(path: 'date.config')]
class DateTimeParams
{
    public function __construct(public \DateTimeInterface $date)
    {
    }
}

#[MapParameters(path: 'year.config')]
class YearParams
{
    public function __construct(public \DateTimeImmutable $date)
    {
    }
}

#[MapParameters(path: 'snake.config')]
class SnakeCaseParams
{
    public function __construct(public string $databaseHost)
    {
    }
}

#[MapParameters(path: 'flat')]
class FlatParams
{
    public function __construct(public string $name, public int $timeout)
    {
    }
}

#[MapParameters(path: 'default.config')]
class DefaultValueParams
{
    public function __construct(public string $required, public string $optional = 'default')
    {
    }
}

#[MapParameters(path: 'incomplete')]
class MissingRequiredParams
{
    public function __construct(public string $name, public string $required)
    {
    }
}

#[MapParameters(path: 'extra')]
class UnrecognizedParams
{
    public function __construct(public string $name)
    {
    }
}

#[MapParameters(path: 'circular')]
class CircularA
{
    public function __construct(public CircularB $child)
    {
    }
}

class CircularB
{
    public function __construct(public CircularA $parent)
    {
    }
}
