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

class RouteCompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCompileData
     */
    public function testCompile($name, $arguments, $prefix, $regex, $variables, $tokens)
    {
        $r = new \ReflectionClass('Symfony\\Component\\Routing\\Route');
        $route = $r->newInstanceArgs($arguments);

        $compiled = $route->compile();
        $this->assertEquals($prefix, $compiled->getStaticPrefix(), $name.' (static prefix)');
        $this->assertEquals($regex, $compiled->getRegex(), $name.' (regex)');
        $this->assertEquals($variables, $compiled->getVariables(), $name.' (variables)');
        $this->assertEquals($tokens, $compiled->getTokens(), $name.' (tokens)');
    }

    public function provideCompileData()
    {
        return array(
            array(
                'Static route',
                array('/foo'),
                '/foo', '#^/foo$#s', array(), array(
                    array('text', '/foo'),
                )),

            array(
                'Route with a variable',
                array('/foo/{bar}'),
                '/foo', '#^/foo/(?P<bar>[^/]+?)$#s', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with a variable that has a default value',
                array('/foo/{bar}', array('bar' => 'bar')),
                '/foo', '#^/foo(?:/(?P<bar>[^/]+?))?$#s', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables',
                array('/foo/{bar}/{foobar}'),
                '/foo', '#^/foo/(?P<bar>[^/]+?)/(?P<foobar>[^/]+?)$#s', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables that have default values',
                array('/foo/{bar}/{foobar}', array('bar' => 'bar', 'foobar' => '')),
                '/foo', '#^/foo(?:/(?P<bar>[^/]+?)(?:/(?P<foobar>[^/]+?))?)?$#s', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with several variables but some of them have no default values',
                array('/foo/{bar}/{foobar}', array('bar' => 'bar')),
                '/foo', '#^/foo/(?P<bar>[^/]+?)/(?P<foobar>[^/]+?)$#s', array('bar', 'foobar'), array(
                    array('variable', '/', '[^/]+?', 'foobar'),
                    array('variable', '/', '[^/]+?', 'bar'),
                    array('text', '/foo'),
                )),

            array(
                'Route with an optional variable as the first segment',
                array('/{bar}', array('bar' => 'bar')),
                '', '#^/(?P<bar>[^/]+?)?$#s', array('bar'), array(
                    array('variable', '/', '[^/]+?', 'bar'),
                )),

            array(
                'Route with an optional variable as the first segment with requirements',
                array('/{bar}', array('bar' => 'bar'), array('bar' => '(foo|bar)')),
                '', '#^/(?P<bar>(foo|bar))?$#s', array('bar'), array(
                    array('variable', '/', '(foo|bar)', 'bar'),
                )),
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testRouteWithSameVariableTwice()
    {
        $route = new Route('/{name}/{name}');

        $compiled = $route->compile();
    }
}
