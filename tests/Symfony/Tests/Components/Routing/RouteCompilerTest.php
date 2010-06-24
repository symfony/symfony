<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Routing;

use Symfony\Components\Routing\Route;

require __DIR__.'/RouteCompiler.php';

class RouteCompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCompileData
     */
    public function testCompile($name, $arguments, $prefix, $regex, $variables, $tokens)
    {
        $r = new \ReflectionClass('Symfony\\Components\\Routing\\Route');
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
                '/foo', '#^/foo$#x', array(), array(
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with a variable',
                array('/foo/:bar'),
                '/foo', '#^/foo/(?P<bar>[^/\.]+?)$#x', array('bar' => ':bar'), array(
                    array('variable', '/', ':bar', 'bar'),
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with a variable that has a default value',
                array('/foo/:bar', array('bar' => 'bar')),
                '/foo', '#^/foo(?:/(?P<bar>[^/\.]+?))?$#x', array('bar' => ':bar'), array(
                    array('variable', '/', ':bar', 'bar'),
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with several variables',
                array('/foo/:bar/:foobar'),
                '/foo', '#^/foo/(?P<bar>[^/\.]+?)/(?P<foobar>[^/\.]+?)$#x', array('bar' => ':bar', 'foobar' => ':foobar'), array(
                    array('variable', '/', ':foobar', 'foobar'),
                    array('variable', '/', ':bar', 'bar'),
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with several variables that have default values',
                array('/foo/:bar/:foobar', array('bar' => 'bar', 'foobar' => 'foobar')),
                '/foo', '#^/foo(?:/(?P<bar>[^/\.]+?) (?:/(?P<foobar>[^/\.]+?) )?)?$#x', array('bar' => ':bar', 'foobar' => ':foobar'), array(
                    array('variable', '/', ':foobar', 'foobar'),
                    array('variable', '/', ':bar', 'bar'),
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with several variables but some of them have no default values',
                array('/foo/:bar/:foobar', array('bar' => 'bar')),
                '/foo', '#^/foo/(?P<bar>[^/\.]+?)/(?P<foobar>[^/\.]+?)$#x', array('bar' => ':bar', 'foobar' => ':foobar'), array(
                    array('variable', '/', ':foobar', 'foobar'),
                    array('variable', '/', ':bar', 'bar'),
                    array('text', '/', 'foo', null),
                )),

            array(
                'Route with a custom token',
                array('/=foo', array(), array(), array('compiler_class' => 'Symfony\\Tests\\Components\\Routing\\RouteCompiler')),
                '', '#^/foo/(?P<foo>[^/\.]+?)$#x', array('foo' => '=foo'), array(
                    array('label', '/', '=foo', 'foo'),
                )),
        );
    }
}
