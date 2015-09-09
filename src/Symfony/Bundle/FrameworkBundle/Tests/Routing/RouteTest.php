<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\Route;

class RouteTest extends \PHPUnit_Framework_TestCase
{
    public function testSetController()
    {
        $route = new Route('/foo');
        $route->setController('AppBundle:Pet:puppy');

        $this->assertEquals('AppBundle:Pet:puppy', $route->getDefault('_controller'));
    }

    public function testSetRequestFormat()
    {
        $route = new Route('/foo');
        $route->setRequestFormat('json');

        $this->assertEquals('json', $route->getDefault('_format'));
    }

    public function testSetLocale()
    {
        $route = new Route('/foo');
        $route->setLocale('de');

        $this->assertEquals('de', $route->getDefault('_locale'));
    }

    public function testSetName()
    {
        $route = new Route('/foo');
        $route->setName('foo_route');

        $this->assertEquals('foo_route', $route->getName());
    }

    /**
     * @dataProvider provideRouteAndExpectedRouteName
     */
    public function testDefaultRouteNameGeneration(Route $route, $expectedRouteName)
    {
        $this->assertEquals($expectedRouteName, $route->generateRouteName());
    }

    public function provideRouteAndExpectedRouteName()
    {
        return array(
            array(new Route('/Invalid%Symbols#Stripped', array(), array(), array(), '', array(), array('POST')), 'POST_InvalidSymbolsStripped'),
            array(new Route('/post/{id}', array(), array(), array(), '', array(), array('GET')), 'GET_post_id'),
            array(new Route('/colon:pipe|dashes-escaped'), '_colon_pipe_dashes_escaped'),
            array(new Route('/underscores_and.periods'), '_underscores_and.periods'),
        );
    }
}
