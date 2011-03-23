<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Generator;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;

class UrlGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var RouteCollection */
    private $routeCollection;
    /** @var UrlGenerator */
    private $generator;

    protected function setUp()
    {
        parent::setUp();

        $this->routeCollection = new RouteCollection();
        $this->generator = new UrlGenerator($this->routeCollection);
    }

    public function testAbsoluteUrlWithPort80()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>80,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array(), true);

        $this->assertEquals('http://localhost/app.php/testing', $url);
    }

    public function testAbsoluteSecureUrlWithPort443()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>443,
            'is_secure'=>true));

        $url = $this->generator->generate('test', array(), true);

        $this->assertEquals('https://localhost/app.php/testing', $url);
    }

    public function testAbsoluteUrlWithNonStandardPort()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>8080,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array(), true);

        $this->assertEquals('http://localhost:8080/app.php/testing', $url);
    }

    public function testAbsoluteSecureUrlWithNonStandardPort()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>8080,
            'is_secure'=>true));

        $url = $this->generator->generate('test', array(), true);

        $this->assertEquals('https://localhost:8080/app.php/testing', $url);
    }

    public function testRelativeUrlWithoutParameters()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>80,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array(), false);

        $this->assertEquals('/app.php/testing', $url);
    }
    
    public function testRelativeUrlWithParameter()
    {
        $this->routeCollection->add('test', new Route('/testing/{foo}'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>80,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array('foo' => 'bar'), false);

        $this->assertEquals('/app.php/testing/bar', $url);
    }
    
    public function testRelativeUrlWithExtraParameters()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>80,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array('foo' => 'bar'), false);

        $this->assertEquals('/app.php/testing?foo=bar', $url);
    }
    
    public function testAbsoluteUrlWithExtraParameters()
    {
        $this->routeCollection->add('test', new Route('/testing'));
        $this->generator->setContext(array(
            'base_url'=>'/app.php',
            'method'=>'GET',
            'host'=>'localhost',
            'port'=>80,
            'is_secure'=>false));

        $url = $this->generator->generate('test', array('foo' => 'bar'), true);

        $this->assertEquals('http://localhost/app.php/testing?foo=bar', $url);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateWithoutRoutes()
    {       
        $this->generator->generate('test', array(), true);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateForRouteWithoutManditoryParameter()
    {
        $this->routeCollection->add('test', new Route('/testing/{foo}'));
        $this->generator->generate('test', array(), true);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateForRouteWithInvalidOptionalParameter()
    {
        $route = new Route('/testing/{foo}', array('foo' => '1'), array('foo' => 'd+'));
        $this->routeCollection->add('test', $route);
        $this->generator->generate('test', array('foo' => 'bar'), true);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGenerateForRouteWithInvalidManditoryParameter()
    {
        $route = new Route('/testing/{foo}', array(), array('foo' => 'd+'));
        $this->routeCollection->add('test', $route);
        $this->generator->generate('test', array('foo' => 'bar'), true);
    } 
}
