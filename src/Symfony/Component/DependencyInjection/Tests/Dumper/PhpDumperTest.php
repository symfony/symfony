<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Dumper;

use DummyProxyDumper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Variable;
use Symfony\Component\ExpressionLanguage\Expression;

require_once __DIR__.'/../Fixtures/includes/classes.php';

class PhpDumperTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $container = new ContainerBuilder();
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services1.php', $dumper->dump(), '->dump() dumps an empty container as an empty PHP class');
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services1-1.php', $dumper->dump(array('class' => 'Container', 'base_class' => 'AbstractContainer', 'namespace' => 'Symfony\Component\DependencyInjection\Dump')), '->dump() takes a class and a base_class options');

        $container = new ContainerBuilder();
        $container->compile();
        new PhpDumper($container);
    }

    public function testDumpOptimizationString()
    {
        $definition = new Definition();
        $definition->setClass('stdClass');
        $definition->addArgument(array(
            'only dot' => '.',
            'concatenation as value' => '.\'\'.',
            'concatenation from the start value' => '\'\'.',
            '.' => 'dot as a key',
            '.\'\'.' => 'concatenation as a key',
            '\'\'.' => 'concatenation from the start key',
            'optimize concatenation' => 'string1%some_string%string2',
            'optimize concatenation with empty string' => 'string1%empty_value%string2',
            'optimize concatenation from the start' => '%empty_value%start',
            'optimize concatenation at the end' => 'end%empty_value%',
        ));

        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->setDefinition('test', $definition);
        $container->setParameter('empty_value', '');
        $container->setParameter('some_string', '-');
        $container->compile();

        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services10.php', $dumper->dump(), '->dump() dumps an empty container as an empty PHP class');
    }

    public function testDumpRelativeDir()
    {
        $definition = new Definition();
        $definition->setClass('stdClass');
        $definition->addArgument('%foo%');
        $definition->addArgument(array('%foo%' => '%buz%/'));

        $container = new ContainerBuilder();
        $container->setDefinition('test', $definition);
        $container->setParameter('foo', 'wiz'.dirname(__DIR__));
        $container->setParameter('bar', __DIR__);
        $container->setParameter('baz', '%bar%/PhpDumperTest.php');
        $container->setParameter('buz', dirname(dirname(__DIR__)));
        $container->compile();

        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services12.php', $dumper->dump(array('file' => __FILE__)), '->dump() dumps __DIR__ relative strings');
    }

    /**
     * @dataProvider provideInvalidParameters
     * @expectedException \InvalidArgumentException
     */
    public function testExportParameters($parameters)
    {
        $container = new ContainerBuilder(new ParameterBag($parameters));
        $container->compile();
        $dumper = new PhpDumper($container);
        $dumper->dump();
    }

    public function provideInvalidParameters()
    {
        return array(
            array(array('foo' => new Definition('stdClass'))),
            array(array('foo' => new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")'))),
            array(array('foo' => new Reference('foo'))),
            array(array('foo' => new Variable('foo'))),
        );
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $container->compile();
        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services8.php', $dumper->dump(), '->dump() dumps parameters');
    }

    /**
     * @group legacy
     * @expectedDeprecation Dumping an uncompiled ContainerBuilder is deprecated since version 3.3 and will not be supported anymore in 4.0. Compile the container beforehand.
     */
    public function testAddServiceWithoutCompilation()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new PhpDumper($container);
        $this->assertEquals(str_replace('%path%', str_replace('\\', '\\\\', self::$fixturesPath.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR), file_get_contents(self::$fixturesPath.'/php/services9.php')), $dumper->dump(), '->dump() dumps services');
    }

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $container->compile();
        $dumper = new PhpDumper($container);
        $this->assertEquals(str_replace('%path%', str_replace('\\', '\\\\', self::$fixturesPath.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR), file_get_contents(self::$fixturesPath.'/php/services9_compiled.php')), $dumper->dump(), '->dump() dumps services');

        $container = new ContainerBuilder();
        $container->register('foo', 'FooClass')->addArgument(new \stdClass());
        $container->compile();
        $dumper = new PhpDumper($container);
        try {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Symfony\Component\DependencyInjection\Exception\RuntimeException', $e, '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource.', $e->getMessage(), '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
    }

    public function testServicesWithAnonymousFactories()
    {
        $container = include self::$fixturesPath.'/containers/container19.php';
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services19.php', $dumper->dump(), '->dump() dumps services with anonymous factories');
    }

    public function testAddServiceIdWithUnsupportedCharacters()
    {
        $class = 'Symfony_DI_PhpDumper_Test_Unsupported_Characters';
        $container = new ContainerBuilder();
        $container->register('bar$', 'FooClass');
        $container->register('bar$!', 'FooClass');
        $container->compile();
        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => $class)));

        $this->assertTrue(method_exists($class, 'getBarService'));
        $this->assertTrue(method_exists($class, 'getBar2Service'));
    }

    public function testConflictingServiceIds()
    {
        $class = 'Symfony_DI_PhpDumper_Test_Conflicting_Service_Ids';
        $container = new ContainerBuilder();
        $container->register('foo_bar', 'FooClass');
        $container->register('foobar', 'FooClass');
        $container->compile();
        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => $class)));

        $this->assertTrue(method_exists($class, 'getFooBarService'));
        $this->assertTrue(method_exists($class, 'getFoobar2Service'));
    }

    public function testConflictingMethodsWithParent()
    {
        $class = 'Symfony_DI_PhpDumper_Test_Conflicting_Method_With_Parent';
        $container = new ContainerBuilder();
        $container->register('bar', 'FooClass');
        $container->register('foo_bar', 'FooClass');
        $container->compile();
        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array(
            'class' => $class,
            'base_class' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\containers\CustomContainer',
        )));

        $this->assertTrue(method_exists($class, 'getBar2Service'));
        $this->assertTrue(method_exists($class, 'getFoobar2Service'));
    }

    /**
     * @dataProvider provideInvalidFactories
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Cannot dump definition
     */
    public function testInvalidFactories($factory)
    {
        $container = new ContainerBuilder();
        $def = new Definition('stdClass');
        $def->setFactory($factory);
        $container->setDefinition('bar', $def);
        $container->compile();
        $dumper = new PhpDumper($container);
        $dumper->dump();
    }

    public function provideInvalidFactories()
    {
        return array(
            array(array('', 'method')),
            array(array('class', '')),
            array(array('...', 'method')),
            array(array('class', '...')),
        );
    }

    public function testAliases()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $container->compile();
        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Aliases')));

        $container = new \Symfony_DI_PhpDumper_Test_Aliases();
        $container->set('foo', $foo = new \stdClass());
        $this->assertSame($foo, $container->get('foo'));
        $this->assertSame($foo, $container->get('alias_for_foo'));
        $this->assertSame($foo, $container->get('alias_for_alias'));
    }

    public function testFrozenContainerWithoutAliases()
    {
        $container = new ContainerBuilder();
        $container->compile();

        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Frozen_No_Aliases')));

        $container = new \Symfony_DI_PhpDumper_Test_Frozen_No_Aliases();
        $this->assertFalse($container->has('foo'));
    }

    public function testOverrideServiceWhenUsingADumpedContainer()
    {
        require_once self::$fixturesPath.'/php/services9.php';
        require_once self::$fixturesPath.'/includes/foo.php';

        $container = new \ProjectServiceContainer();
        $container->set('bar', $bar = new \stdClass());
        $container->setParameter('foo_bar', 'foo_bar');

        $this->assertSame($bar, $container->get('bar'), '->set() overrides an already defined service');
    }

    public function testOverrideServiceWhenUsingADumpedContainerAndServiceIsUsedFromAnotherOne()
    {
        require_once self::$fixturesPath.'/php/services9.php';
        require_once self::$fixturesPath.'/includes/foo.php';
        require_once self::$fixturesPath.'/includes/classes.php';

        $container = new \ProjectServiceContainer();
        $container->set('bar', $bar = new \stdClass());

        $this->assertSame($bar, $container->get('foo')->bar, '->set() overrides an already defined service');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function testCircularReference()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->addArgument(new Reference('bar'));
        $container->register('bar', 'stdClass')->setPublic(false)->addMethodCall('setA', array(new Reference('baz')));
        $container->register('baz', 'stdClass')->addMethodCall('setA', array(new Reference('foo')));
        $container->compile();

        $dumper = new PhpDumper($container);
        $dumper->dump();
    }

    public function testDumpAutowireData()
    {
        $container = include self::$fixturesPath.'/containers/container24.php';
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services24.php', $dumper->dump());
    }

    public function testDumpOverridenGetters()
    {
        $container = include self::$fixturesPath.'/containers/container29.php';
        $container->compile();
        $container->getDefinition('foo')
            ->setOverriddenGetter('getInvalid', array(new Reference('bar', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)));
        $dumper = new PhpDumper($container);

        $dump = $dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Overriden_Getters'));
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services29.php', $dump);
        $res = $container->getResources();
        $this->assertSame('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Container29\Foo', (string) array_pop($res));

        eval('?>'.$dump);

        $container = new \Symfony_DI_PhpDumper_Test_Overriden_Getters();

        $foo = $container->get('foo');

        $this->assertSame('public', $foo->getPublic());
        $this->assertSame('protected', $foo->getGetProtected());
        $this->assertSame($foo, $foo->getSelf());
        $this->assertSame(456, $foo->getInvalid());

        $baz = $container->get('baz');
        $r = new \ReflectionMethod($baz, 'getBaz');
        $r->setAccessible(true);

        $this->assertTrue($r->isProtected());
        $this->assertSame('baz', $r->invoke($baz));
    }

    public function testDumpOverridenGettersWithConstructor()
    {
        $container = include self::$fixturesPath.'/containers/container_dump_overriden_getters_with_constructor.php';
        $container->compile();
        $container->getDefinition('foo')
            ->setOverriddenGetter('getInvalid', array(new Reference('bar', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE)));
        $dumper = new PhpDumper($container);

        $dump = $dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Overriden_Getters_With_Constructor'));
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services_dump_overriden_getters_with_constructor.php', $dump);
        $res = $container->getResources();
        $this->assertSame('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Container34\Foo', (string) array_pop($res));

        $baz = $container->get('baz');
        $r = new \ReflectionMethod($baz, 'getBaz');
        $r->setAccessible(true);

        $this->assertTrue($r->isProtected());
        $this->assertSame('baz', $r->invoke($baz));
    }

    /**
     * @dataProvider provideBadOverridenGetters
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testBadOverridenGetters($expectedMessage, $getter, $id = 'foo')
    {
        $container = include self::$fixturesPath.'/containers/container30.php';
        $container->getDefinition($id)->setOverriddenGetter($getter, 123);

        $container->compile();
        $dumper = new PhpDumper($container);

        $this->setExpectedException(RuntimeException::class, $expectedMessage);
        $dumper->dump();
    }

    public function provideBadOverridenGetters()
    {
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getnotfound" does not exist.', 'getNotFound');
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getPrivate" must be public or protected.', 'getPrivate');
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getStatic" cannot be static.', 'getStatic');
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getFinal" cannot be marked as final.', 'getFinal');
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getRef" cannot return by reference.', 'getRef');
        yield array('Unable to configure getter injection for service "foo": method "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Foo::getParam" cannot have any arguments.', 'getParam');
        yield array('Unable to configure getter injection for service "bar": class "Symfony\Component\DependencyInjection\Tests\Fixtures\Container30\Bar" cannot be marked as final.', 'getParam', 'bar');
        yield array('Cannot dump definition for service "baz": factories and overridden getters are incompatible with each other.', 'getParam', 'baz');
    }

    public function testEnvParameter()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services26.yml');
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services26.php', $dumper->dump(), '->dump() dumps inline definitions which reference service_container');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\EnvParameterException
     * @expectedExceptionMessage Incompatible use of dynamic environment variables "FOO" found in parameters.
     */
    public function testUnusedEnvParameter()
    {
        $container = new ContainerBuilder();
        $container->getParameter('env(FOO)');
        $container->compile();
        $dumper = new PhpDumper($container);
        $dumper->dump();
    }

    public function testInlinedDefinitionReferencingServiceContainer()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->addMethodCall('add', array(new Reference('service_container')))->setPublic(false);
        $container->register('bar', 'stdClass')->addArgument(new Reference('foo'));
        $container->compile();

        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services13.php', $dumper->dump(), '->dump() dumps inline definitions which reference service_container');
    }

    public function testInitializePropertiesBeforeMethodCalls()
    {
        require_once self::$fixturesPath.'/includes/classes.php';

        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass');
        $container->register('bar', 'MethodCallClass')
            ->setProperty('simple', 'bar')
            ->setProperty('complex', new Reference('foo'))
            ->addMethodCall('callMe');
        $container->compile();

        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Properties_Before_Method_Calls')));

        $container = new \Symfony_DI_PhpDumper_Test_Properties_Before_Method_Calls();
        $this->assertTrue($container->get('bar')->callPassed(), '->dump() initializes properties before method calls');
    }

    public function testCircularReferenceAllowanceForLazyServices()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')->addArgument(new Reference('bar'));
        $container->register('bar', 'stdClass')->setLazy(true)->addArgument(new Reference('foo'));
        $container->compile();

        $dumper = new PhpDumper($container);
        $dumper->dump();
    }

    public function testCircularReferenceAllowanceForInlinedDefinitionsForLazyServices()
    {
        /*
         *   test graph:
         *              [connection] -> [event_manager] --> [entity_manager](lazy)
         *                                                           |
         *                                                           --(call)- addEventListener ("@lazy_service")
         *
         *              [lazy_service](lazy) -> [entity_manager](lazy)
         *
         */

        $container = new ContainerBuilder();

        $eventManagerDefinition = new Definition('stdClass');

        $connectionDefinition = $container->register('connection', 'stdClass');
        $connectionDefinition->addArgument($eventManagerDefinition);

        $container->register('entity_manager', 'stdClass')
            ->setLazy(true)
            ->addArgument(new Reference('connection'));

        $lazyServiceDefinition = $container->register('lazy_service', 'stdClass');
        $lazyServiceDefinition->setLazy(true);
        $lazyServiceDefinition->addArgument(new Reference('entity_manager'));

        $eventManagerDefinition->addMethodCall('addEventListener', array(new Reference('lazy_service')));

        $container->compile();

        $dumper = new PhpDumper($container);

        $dumper->setProxyDumper(new DummyProxyDumper());
        $dumper->dump();
    }

    public function testLazyArgumentProvideGenerator()
    {
        require_once self::$fixturesPath.'/includes/classes.php';

        $container = new ContainerBuilder();
        $container->register('lazy_referenced', 'stdClass');
        $container
            ->register('lazy_context', 'LazyContext')
            ->setArguments(array(new IteratorArgument(array('foo', new Reference('lazy_referenced'), 'k1' => array('foo' => 'bar'), true, 'k2' => new Reference('service_container')))))
        ;
        $container->compile();

        $dumper = new PhpDumper($container);
        eval('?>'.$dumper->dump(array('class' => 'Symfony_DI_PhpDumper_Test_Lazy_Argument_Provide_Generator')));

        $container = new \Symfony_DI_PhpDumper_Test_Lazy_Argument_Provide_Generator();
        $lazyContext = $container->get('lazy_context');

        $this->assertInstanceOf(RewindableGenerator::class, $lazyContext->lazyValues);

        $i = -1;
        foreach ($lazyContext->lazyValues as $k => $v) {
            switch (++$i) {
                case 0:
                    $this->assertEquals(0, $k);
                    $this->assertEquals('foo', $v);
                    break;
                case 1:
                    $this->assertEquals(1, $k);
                    $this->assertInstanceOf('stdClass', $v);
                    break;
                case 2:
                    $this->assertEquals('k1', $k);
                    $this->assertEquals(array('foo' => 'bar'), $v);
                    break;
                case 3:
                    $this->assertEquals(2, $k);
                    $this->assertTrue($v);
                    break;
                case 4:
                    $this->assertEquals('k2', $k);
                    $this->assertInstanceOf('\Symfony_DI_PhpDumper_Test_Lazy_Argument_Provide_Generator', $v);
                    break;
            }
        }
    }

    public function testClosureProxy()
    {
        $container = include self::$fixturesPath.'/containers/container31.php';
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services31.php', $dumper->dump());
        $res = $container->getResources();
        $this->assertSame('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Container31\Foo', (string) array_pop($res));
    }

    /**
     * @requires PHP 7.1
     */
    public function testClosureProxyPhp71()
    {
        $container = include self::$fixturesPath.'/containers/container32.php';
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services32.php', $dumper->dump());
        $res = $container->getResources();
        $this->assertSame('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Container32\Foo', (string) array_pop($res));
    }

    public function testNormalizedId()
    {
        $container = include self::$fixturesPath.'/containers/container33.php';
        $container->compile();
        $dumper = new PhpDumper($container);

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services33.php', $dumper->dump());
    }
}
