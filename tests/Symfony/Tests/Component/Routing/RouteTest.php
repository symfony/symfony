<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing;

use Symfony\Component\Routing\Route;

class RouteTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $route = new Route('/{foo}', array('foo' => 'bar'), array('foo' => '\d+'), array('foo' => 'bar'));
        $this->assertEquals('/{foo}', $route->getPattern(), '__construct() takes a pattern as its first argument');
        $this->assertEquals(array('foo' => 'bar'), $route->getDefaults(), '__construct() takes defaults as its second argument');
        $this->assertEquals(array('foo' => '\d+'), $route->getRequirements(), '__construct() takes requirements as its third argument');
        $this->assertEquals('bar', $route->getOption('foo'), '__construct() takes options as its fourth argument');
    }

    public function testPattern()
    {
        $route = new Route('/{foo}');
        $route->setPattern('/{bar}');
        $this->assertEquals('/{bar}', $route->getPattern(), '->setPattern() sets the pattern');
        $route->setPattern('');
        $this->assertEquals('/', $route->getPattern(), '->setPattern() adds a / at the beginning of the pattern if needed');
        $route->setPattern('bar');
        $this->assertEquals('/bar', $route->getPattern(), '->setPattern() adds a / at the beginning of the pattern if needed');
        $this->assertEquals($route, $route->setPattern(''), '->setPattern() implements a fluent interface');
    }

    public function testOptions()
    {
        $route = new Route('/{foo}');
        $route->setOptions(array('foo' => 'bar'));
        $this->assertEquals(array_merge(array(
        'compiler_class'     => 'Symfony\\Component\\Routing\\RouteCompiler',
        ), array('foo' => 'bar')), $route->getOptions(), '->setOptions() sets the options');
        $this->assertEquals($route, $route->setOptions(array()), '->setOptions() implements a fluent interface');
    }

    /**
     * @covers Symfony\Component\Routing\Route::setDefaults
     * @covers Symfony\Component\Routing\Route::getDefaults
     * @covers Symfony\Component\Routing\Route::setDefault
     * @covers Symfony\Component\Routing\Route::getDefault
     */
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
        $this->assertNull($route->getDefault('not_defined'), '->getDefault() return null if default value is not setted');

        $route->setDefault('_controller', $closure = function () { return 'Hello'; });
        $this->assertEquals($closure, $route->getDefault('_controller'), '->setDefault() sets a default value');
    }

    public function testRequirements()
    {
        $route = new Route('/{foo}');
        $route->setRequirements(array('foo' => '\d+'));
        $this->assertEquals(array('foo' => '\d+'), $route->getRequirements(), '->setRequirements() sets the requirements');
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->getRequirement() returns a requirement');
        $this->assertNull($route->getRequirement('bar'), '->getRequirement() returns null if a requirement is not defined');
        $route->setRequirements(array('foo' => '^\d+$'));
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->getRequirement() removes ^ and $ from the pattern');
        $this->assertEquals($route, $route->setRequirements(array()), '->setRequirements() implements a fluent interface');
    }

    public function testRequirement()
    {
        $route = new Route('/{foo}');
        $route->setRequirement('foo', '^\d+$');
        $this->assertEquals('\d+', $route->getRequirement('foo'), '->setRequirement() removes ^ and $ from the pattern');
    }

    public function testCompile()
    {
        $route = new Route('/{foo}');
        $this->assertEquals('Symfony\\Component\\Routing\\CompiledRoute', get_class($compiled = $route->compile()), '->compile() returns a compiled route');
        $this->assertEquals($compiled, $route->compile(), '->compile() only compiled the route once');
    }
}
