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

use Symfony\Components\Routing\CompiledRoute;
use Symfony\Components\Routing\Route;

class CompiledRouteTest extends \PHPUnit_Framework_TestCase
{
    public function testAccessors()
    {
        $route = new Route('/:foo', array('foo' => 'bar'), array('foo' => '\d+'), array('foo' => 'bar'));

        $compiled = new CompiledRoute($route, 'prefix', 'regex', array('tokens'), array('variables'));
        $this->assertEquals($route, $compiled->getRoute(), '__construct() takes a route as its first argument');
        $this->assertEquals('prefix', $compiled->getStaticPrefix(), '__construct() takes a static prefix as its second argument');
        $this->assertEquals('regex', $compiled->getRegex(), '__construct() takes a regexp as its third argument');
        $this->assertEquals(array('tokens'), $compiled->getTokens(), '__construct() takes an array of tokens as its fourth argument');
        $this->assertEquals(array('variables'), $compiled->getVariables(), '__construct() takes an array of variables as its fith argument');
    }

    public function testgetPatterngetDefaultsgetOptionsgetRequirements()
    {
        $route = new Route('/:foo', array('foo' => 'bar'), array('foo' => '\d+'), array('foo' => 'bar'));

        $compiled = new CompiledRoute($route, 'prefix', 'regex', array('tokens'), array('variables'));
        $this->assertEquals('/:foo', $compiled->getPattern(), '->getPattern() returns the route pattern');
        $this->assertEquals(array('foo' => 'bar'), $compiled->getDefaults(), '->getDefaults() returns the route defaults');
        $this->assertEquals(array('foo' => '\d+'), $compiled->getRequirements(), '->getRequirements() returns the route requirements');
        $this->assertEquals(array_merge(array(
            'variable_prefixes'  => array(':'),
            'segment_separators' => array('/', '.'),
            'variable_regex'     => '[\w\d_]+',
            'text_regex'         => '.+?',
            'compiler_class'     => 'Symfony\\Components\\Routing\\RouteCompiler',
        ), array('foo' => 'bar')), $compiled->getOptions(), '->getOptions() returns the route options');
    }
}
