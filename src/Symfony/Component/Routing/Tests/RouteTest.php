<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

class RouteTest extends TestCase
{
    public function testConstructor()
    {
        $route = new Route('/{foo}', array('foo' => 'bar'), array('foo' => '\d+'), array('foo' => 'bar'), '{locale}.example.com');
        $this->assertEquals('/{foo}', $route->getPath(), '__construct() takes a path as its first argument');
        $this->assertEquals(array('foo' => 'bar'), $route->getDefaults(), '__construct() takes defaults as its second argument');
        $this->assertEquals(array('foo' => '\d+'), $route->getRequirements(), '__construct() takes requirements as its third argument');
        $this->assertEquals('bar', $route->getOption('foo'), '__construct() takes options as its fourth argument');
        $this->assertEquals('{locale}.example.com', $route->getHost(), '__construct() takes a host pattern as its fifth argument');

        $route = new Route('/', array(), array(), array(), '', array('Https'), array('POST', 'put'), 'context.getMethod() == "GET"');
        $this->assertEquals(array('https'), $route->getSchemes(), '__construct() takes schemes as its sixth argument and lowercases it');
        $this->assertEquals(array('POST', 'PUT'), $route->getMethods(), '__construct() takes methods as its seventh argument and uppercases it');
        $this->assertEquals('context.getMethod() == "GET"', $route->getCondition(), '__construct() takes a condition as its eight argument');

        $route = new Route('/', array(), array(), array(), '', 'Https', 'Post');
        $this->assertEquals(array('https'), $route->getSchemes(), '__construct() takes a single scheme as its sixth argument');
        $this->assertEquals(array('POST'), $route->getMethods(), '__construct() takes a single method as its seventh argument');
    }

    public function testPath()
    {
        $route = new Route('/{foo}');
        $route->setPath('/{bar}');
        $this->assertEquals('/{bar}', $route->getPath(), '->setPath() sets the path');
        $route->setPath('');
        $this->assertEquals('/', $route->getPath(), '->setPath() adds a / at the beginning of the path if needed');
        $route->setPath('bar');
        $this->assertEquals('/bar', $route->getPath(), '->setPath() adds a / at the beginning of the path if needed');
        $this->assertEquals($route, $route->setPath(''), '->setPath() implements a fluent interface');
        $route->setPath('//path');
        $this->assertEquals('/path', $route->getPath(), '->setPath() does not allow two slashes "//" at the beginning of the path as it would be confused with a network path when generating the path from the route');
    }

    public function testOptions()
    {
        $route = new Route('/{foo}');
        $route->setOptions(array('foo' => 'bar'));
        $this->assertEquals(array_merge(array(
        'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',
        ), array('foo' => 'bar')), $route->getOptions(), '->setOptions() sets the options');
        $this->assertEquals($route, $route->setOptions(array()), '->setOptions() implements a fluent interface');

        $route->setOptions(array('foo' => 'foo'));
        $route->addOptions(array('bar' => 'bar'));
        $this->assertEquals($route, $route->addOptions(array()), '->addOptions() implements a fluent interface');
        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar', 'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler'), $route->getOptions(), '->addDefaults() keep previous defaults');
    }

    public function testOption()
    {
        $route = new Route('/{foo}');
        $this->assertFalse($route->hasOption('foo'), '->hasOption() return false if option is not set');
        $this->assertEquals($route, $route->setOption('foo', 'bar'), '->setOption() implements a fluent interface');
        $this->assertEquals('bar', $route->getOption('foo'), '->setOption() sets the option');
        $this->assertTrue($route->hasOption('foo'), '->hasOption() return true if option is set');
    }

    public function testDefaults()
    {
        $route = new Route('/{foo}');
        $route->setDefaults(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $route->getDefaults(), '->setDefaults() sets the defaults');
        $this->assertEquals($route, $route->setDefaults(array()), '->setDefaults() implements a fluent interface');

        $route->setDefault('foo', 'bar');
        $this->assertEquals('bar', $route->getDefault('foo'), '->setDefault() sets a default value');

        $route->setDefault('foo2', 'bar2');
        $this->assertEquals('bar2', $route->getDefault('foo2'), '->getDefault() return the default value');
        $this->assertNull($route->getDefault('not_defined'), '->getDefault() return null if default value is not set');

        $route->setDefault('_controller', $closure = function () { return 'Hello'; });
        $this->assertEquals($closure, $route->getDefault('_controller'), '->setDefault() sets a default value');

        $route->setDefaults(array('foo' => 'foo'));
        $route->addDefaults(array('bar' => 'bar'));
        $this->assertEquals($route, $route->addDefaults(array()), '->addDefaults() implements a fluent interface');
        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar'), $route->getDefaults(), '->addDefaults() keep previous defaults');
    }

    public function testRequirements()
    {
        $route = new Route('/{foo}');
        $route->setRequirements(array('foo' => '\d+'));
        $this->assertEquals(array('foo' => '\d+'), $route->getRequirements(), '->setRequirements() sets the requirements');
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->getRequirement() returns a requirement');
        $this->assertNull($route->getRequirement('bar'), '->getRequirement() returns null if a requirement is not defined');
        $route->setRequirements(array('foo' => '^\d+$'));
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->getRequirement() removes ^ and $ from the path');
        $this->assertEquals($route, $route->setRequirements(array()), '->setRequirements() implements a fluent interface');

        $route->setRequirements(array('foo' => '\d+'));
        $route->addRequirements(array('bar' => '\d+'));
        $this->assertEquals($route, $route->addRequirements(array()), '->addRequirements() implements a fluent interface');
        $this->assertEquals(array('foo' => '\d+', 'bar' => '\d+'), $route->getRequirements(), '->addRequirement() keep previous requirements');
    }

    public function testRequirement()
    {
        $route = new Route('/{foo}');
        $this->assertFalse($route->hasRequirement('foo'), '->hasRequirement() return false if requirement is not set');
        $route->setRequirement('foo', '^\d+$');
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->setRequirement() removes ^ and $ from the path');
        $this->assertTrue($route->hasRequirement('foo'), '->hasRequirement() return true if requirement is set');
    }

    /**
     * @dataProvider getInvalidRequirements
     * @expectedException \InvalidArgumentException
     */
    public function testSetInvalidRequirement($req)
    {
        $route = new Route('/{foo}');
        $route->setRequirement('foo', $req);
    }

    public function getInvalidRequirements()
    {
        return array(
           array(''),
           array(array()),
           array('^$'),
           array('^'),
           array('$'),
        );
    }

    public function testHost()
    {
        $route = new Route('/');
        $route->setHost('{locale}.example.net');
        $this->assertEquals('{locale}.example.net', $route->getHost(), '->setHost() sets the host pattern');
    }

    public function testScheme()
    {
        $route = new Route('/');
        $this->assertEquals(array(), $route->getSchemes(), 'schemes is initialized with array()');
        $this->assertFalse($route->hasScheme('http'));
        $route->setSchemes('hTTp');
        $this->assertEquals(array('http'), $route->getSchemes(), '->setSchemes() accepts a single scheme string and lowercases it');
        $this->assertTrue($route->hasScheme('htTp'));
        $this->assertFalse($route->hasScheme('httpS'));
        $route->setSchemes(array('HttpS', 'hTTp'));
        $this->assertEquals(array('https', 'http'), $route->getSchemes(), '->setSchemes() accepts an array of schemes and lowercases them');
        $this->assertTrue($route->hasScheme('htTp'));
        $this->assertTrue($route->hasScheme('httpS'));
    }

    public function testMethod()
    {
        $route = new Route('/');
        $this->assertEquals(array(), $route->getMethods(), 'methods is initialized with array()');
        $route->setMethods('gEt');
        $this->assertEquals(array('GET'), $route->getMethods(), '->setMethods() accepts a single method string and uppercases it');
        $route->setMethods(array('gEt', 'PosT'));
        $this->assertEquals(array('GET', 'POST'), $route->getMethods(), '->setMethods() accepts an array of methods and uppercases them');
    }

    public function testCondition()
    {
        $route = new Route('/');
        $this->assertSame('', $route->getCondition());
        $route->setCondition('context.getMethod() == "GET"');
        $this->assertSame('context.getMethod() == "GET"', $route->getCondition());
    }

    public function testCompile()
    {
        $route = new Route('/{foo}');
        $this->assertInstanceOf('Symfony\Component\Routing\CompiledRoute', $compiled = $route->compile(), '->compile() returns a compiled route');
        $this->assertSame($compiled, $route->compile(), '->compile() only compiled the route once if unchanged');
        $route->setRequirement('foo', '.*');
        $this->assertNotSame($compiled, $route->compile(), '->compile() recompiles if the route was modified');
    }

    public function testSerialize()
    {
        $route = new Route('/prefix/{foo}', array('foo' => 'default'), array('foo' => '\d+'));

        $serialized = serialize($route);
        $unserialized = unserialize($serialized);

        $this->assertEquals($route, $unserialized);
        $this->assertNotSame($route, $unserialized);
    }

    public function testInlineDefaultAndRequirement()
    {
        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', null), new Route('/foo/{bar?}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', 'baz'), new Route('/foo/{bar?baz}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', 'baz<buz>'), new Route('/foo/{bar?baz<buz>}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', 'baz'), new Route('/foo/{bar?}', array('bar' => 'baz')));

        $this->assertEquals((new Route('/foo/{bar}'))->setRequirement('bar', '.*'), new Route('/foo/{bar<.*>}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setRequirement('bar', '>'), new Route('/foo/{bar<>>}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setRequirement('bar', '\d+'), new Route('/foo/{bar<.*>}', array(), array('bar' => '\d+')));
        $this->assertEquals((new Route('/foo/{bar}'))->setRequirement('bar', '[a-z]{2}'), new Route('/foo/{bar<[a-z]{2}>}'));

        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', null)->setRequirement('bar', '.*'), new Route('/foo/{bar<.*>?}'));
        $this->assertEquals((new Route('/foo/{bar}'))->setDefault('bar', '<>')->setRequirement('bar', '>'), new Route('/foo/{bar<>>?<>}'));
    }

    /**
     * Tests that the compiled version is also serialized to prevent the overhead
     * of compiling it again after unserialize.
     */
    public function testSerializeWhenCompiled()
    {
        $route = new Route('/prefix/{foo}', array('foo' => 'default'), array('foo' => '\d+'));
        $route->setHost('{locale}.example.net');
        $route->compile();

        $serialized = serialize($route);
        $unserialized = unserialize($serialized);

        $this->assertEquals($route, $unserialized);
        $this->assertNotSame($route, $unserialized);
    }

    /**
     * Tests that unserialization does not fail when the compiled Route is of a
     * class other than CompiledRoute, such as a subclass of it.
     */
    public function testSerializeWhenCompiledWithClass()
    {
        $route = new Route('/', array(), array(), array('compiler_class' => '\Symfony\Component\Routing\Tests\Fixtures\CustomRouteCompiler'));
        $this->assertInstanceOf('\Symfony\Component\Routing\Tests\Fixtures\CustomCompiledRoute', $route->compile(), '->compile() returned a proper route');

        $serialized = serialize($route);
        try {
            $unserialized = unserialize($serialized);
            $this->assertInstanceOf('\Symfony\Component\Routing\Tests\Fixtures\CustomCompiledRoute', $unserialized->compile(), 'the unserialized route compiled successfully');
        } catch (\Exception $e) {
            $this->fail('unserializing a route which uses a custom compiled route class');
        }
    }

    /**
     * Tests that the serialized representation of a route in one symfony version
     * also works in later symfony versions, i.e. the unserialized route is in the
     * same state as another, semantically equivalent, route.
     */
    public function testSerializedRepresentationKeepsWorking()
    {
        $serialized = 'C:31:"Symfony\Component\Routing\Route":936:{a:8:{s:4:"path";s:13:"/prefix/{foo}";s:4:"host";s:20:"{locale}.example.net";s:8:"defaults";a:1:{s:3:"foo";s:7:"default";}s:12:"requirements";a:1:{s:3:"foo";s:3:"\d+";}s:7:"options";a:1:{s:14:"compiler_class";s:39:"Symfony\Component\Routing\RouteCompiler";}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:8:"compiled";C:39:"Symfony\Component\Routing\CompiledRoute":571:{a:8:{s:4:"vars";a:2:{i:0;s:6:"locale";i:1;s:3:"foo";}s:11:"path_prefix";s:7:"/prefix";s:10:"path_regex";s:31:"#^/prefix(?:/(?P<foo>\d+))?$#sD";s:11:"path_tokens";a:2:{i:0;a:4:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:3:"\d+";i:3;s:3:"foo";}i:1;a:2:{i:0;s:4:"text";i:1;s:7:"/prefix";}}s:9:"path_vars";a:1:{i:0;s:3:"foo";}s:10:"host_regex";s:40:"#^(?P<locale>[^\.]++)\.example\.net$#sDi";s:11:"host_tokens";a:2:{i:0;a:2:{i:0;s:4:"text";i:1;s:12:".example.net";}i:1;a:4:{i:0;s:8:"variable";i:1;s:0:"";i:2;s:7:"[^\.]++";i:3;s:6:"locale";}}s:9:"host_vars";a:1:{i:0;s:6:"locale";}}}}}';
        $unserialized = unserialize($serialized);

        $route = new Route('/prefix/{foo}', array('foo' => 'default'), array('foo' => '\d+'));
        $route->setHost('{locale}.example.net');
        $route->compile();

        $this->assertEquals($route, $unserialized);
        $this->assertNotSame($route, $unserialized);
    }
}
