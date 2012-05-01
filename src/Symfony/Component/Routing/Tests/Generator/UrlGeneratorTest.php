<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Generator;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;

class UrlGeneratorTest extends \PHPUnit_Framework_TestCase
{
    public function testAbsoluteUrlWithPort80()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', array(), true);

        $this->assertEquals('http://localhost/app.php/testing', $url);
    }

    public function testAbsoluteSecureUrlWithPort443()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, array('scheme' => 'https'))->generate('test', array(), true);

        $this->assertEquals('https://localhost/app.php/testing', $url);
    }

    public function testAbsoluteUrlWithNonStandardPort()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, array('httpPort' => 8080))->generate('test', array(), true);

        $this->assertEquals('http://localhost:8080/app.php/testing', $url);
    }

    public function testAbsoluteUrlUnicodeWithNonStandardPort()
    {
        $routes = $this->getRoutes('test', new Route('/Жени'));
        $url = $this->getGenerator($routes, array('httpPort' => 8080))->generate('test', array(), true);

        $this->assertEquals('http://localhost:8080/app.php/%D0%96%D0%B5%D0%BD%D0%B8', $url);
    }

    public function testAbsoluteSecureUrlWithNonStandardPort()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, array('httpsPort' => 8080, 'scheme' => 'https'))->generate('test', array(), true);

        $this->assertEquals('https://localhost:8080/app.php/testing', $url);
    }

    public function testRelativeUrlWithoutParameters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', array(), false);

        $this->assertEquals('/app.php/testing', $url);
    }

    public function testRelativeUrlWithParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), false);

        $this->assertEquals('/app.php/testing/bar', $url);
    }

    public function testRelativeUrlWithNullParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing.{format}', array('format' => null)));
        $url = $this->getGenerator($routes)->generate('test', array(), false);

        $this->assertEquals('/app.php/testing', $url);
    }

    public function testRelativeUrlWithNullParameterButNotOptional()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}/bar', array('foo' => null)));
        $url = $this->getGenerator($routes)->generate('test', array(), false);

        $this->assertEquals('/app.php/testing//bar', $url);
    }

    public function testRelativeUrlWithOptionalZeroParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{page}'));
        $url = $this->getGenerator($routes)->generate('test', array('page' => 0), false);

        $this->assertEquals('/app.php/testing/0', $url);
    }

    public function testRelativeUrlWithExtraParameters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), false);

        $this->assertEquals('/app.php/testing?foo=bar', $url);
    }

    public function testRelativeUrlUnicodeWithExtraParameters()
    {
        $routes = $this->getRoutes('test', new Route('/Жени'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), false);

        $this->assertEquals('/app.php/%D0%96%D0%B5%D0%BD%D0%B8?foo=bar', $url);
    }

    public function testRelativeUrlUnicodeWithExtraUnicodeParameters()
    {
        $routes = $this->getRoutes('test', new Route('/Жени'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => 'शाम'), false);

        $this->assertEquals('/app.php/%D0%96%D0%B5%D0%BD%D0%B8?foo=%E0%A4%B6%E0%A4%BE%E0%A4%AE', $url);
    }

    public function testAbsoluteUrlWithExtraParameters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), true);

        $this->assertEquals('http://localhost/app.php/testing?foo=bar', $url);
    }

    public function testUrlWithNullExtraParameters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', array('foo' => null), true);

        $this->assertEquals('http://localhost/app.php/testing', $url);
    }

    public function testUrlWithExtraParametersFromGlobals()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('bar', 'bar');
        $generator->setContext($context);
        $url = $generator->generate('test', array('foo' => 'bar'));

        $this->assertEquals('/app.php/testing?foo=bar', $url);
    }

    public function testUrlWithGlobalParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('foo', 'bar');
        $generator->setContext($context);
        $url = $generator->generate('test', array());

        $this->assertEquals('/app.php/testing/bar', $url);
    }

    public function testUrlWithGlobalUnicodeParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('foo', 'Жени');
        $generator->setContext($context);
        $url = $generator->generate('test', array());

        $this->assertEquals('/app.php/testing/%D0%96%D0%B5%D0%BD%D0%B8', $url);
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function testGenerateWithoutRoutes()
    {
        $routes = $this->getRoutes('foo', new Route('/testing/{foo}'));
        $this->getGenerator($routes)->generate('test', array(), true);
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     */
    public function testGenerateForRouteWithoutMandatoryParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $this->getGenerator($routes)->generate('test', array(), true);
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function testGenerateForRouteWithInvalidOptionalParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', array('foo' => '1'), array('foo' => 'd+')));
        $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), true);
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function testGenerateForRouteWithInvalidManditoryParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', array(), array('foo' => 'd+')));
        $this->getGenerator($routes)->generate('test', array('foo' => 'bar'), true);
    }

    public function testSchemeRequirementDoesNothingIfSameCurrentScheme()
    {
        $routes = $this->getRoutes('test', new Route('/', array(), array('_scheme' => 'http')));
        $this->assertEquals('/app.php/', $this->getGenerator($routes)->generate('test'));

        $routes = $this->getRoutes('test', new Route('/', array(), array('_scheme' => 'https')));
        $this->assertEquals('/app.php/', $this->getGenerator($routes, array('scheme' => 'https'))->generate('test'));
    }

    public function testSchemeRequirementForcesAbsoluteUrl()
    {
        $routes = $this->getRoutes('test', new Route('/', array(), array('_scheme' => 'https')));
        $this->assertEquals('https://localhost/app.php/', $this->getGenerator($routes)->generate('test'));

        $routes = $this->getRoutes('test', new Route('/', array(), array('_scheme' => 'http')));
        $this->assertEquals('http://localhost/app.php/', $this->getGenerator($routes, array('scheme' => 'https'))->generate('test'));
    }

    public function testNoTrailingSlashForMultipleOptionalParameters()
    {
        $routes = $this->getRoutes('test', new Route('/category/{slug1}/{slug2}/{slug3}', array('slug2' => null, 'slug3' => null)));

        $this->assertEquals('/app.php/category/foo', $this->getGenerator($routes)->generate('test', array('slug1' => 'foo')));
    }

    public function testUnicodeNoTrailingSlashForMultipleOptionalParameters()
    {
        $routes = $this->getRoutes('test', new Route('/Жени/{bulgarian}/{slug2}/{slug3}', array('bulgarian' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%D0%96%D0%B5%D0%BD%D0%B8/%D0%96%D0%B5%D0%BD%D0%B8', $this->getGenerator($routes)->generate('test', array('bulgarian' => 'Жени')));

        $routes = $this->getRoutes('test', new Route('/शाम/{hindi}/{slug2}/{slug3}', array('hindi' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%E0%A4%B6%E0%A4%BE%E0%A4%AE/%E0%A4%B6%E0%A4%BE%E0%A4%AE', $this->getGenerator($routes)->generate('test', array('hindi' => 'शाम')));

        $routes = $this->getRoutes('test', new Route('/مساء/{arabic}/{slug2}/{slug3}', array('arabic' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%D9%85%D8%B3%D8%A7%D8%A1/%D9%85%D8%B3%D8%A7%D8%A1', $this->getGenerator($routes)->generate('test', array('arabic' => 'مساء')));

        $routes = $this->getRoutes('test', new Route('/երեկո/{armenian}/{slug2}/{slug3}', array('armenian' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%D5%A5%D6%80%D5%A5%D5%AF%D5%B8/%D5%A5%D6%80%D5%A5%D5%AF%D5%B8', $this->getGenerator($routes)->generate('test', array('armenian' => 'երեկո')));

        $routes = $this->getRoutes('test', new Route('/黄昏/{chineseSimplified}/{slug2}/{slug3}', array('chineseSimplified' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%E9%BB%84%E6%98%8F/%E9%BB%84%E6%98%8F', $this->getGenerator($routes)->generate('test', array('chineseSimplified' => '黄昏')));

        $routes = $this->getRoutes('test', new Route('/黃昏/{chineseTraditional}/{slug2}/{slug3}', array('chineseTraditional' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%E9%BB%83%E6%98%8F/%E9%BB%83%E6%98%8F', $this->getGenerator($routes)->generate('test', array('chineseTraditional' => '黃昏')));

        $routes = $this->getRoutes('test', new Route('/вечер/{macedonian}/{slug2}/{slug3}', array('macedonian' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%D0%B2%D0%B5%D1%87%D0%B5%D1%80/%D0%B2%D0%B5%D1%87%D0%B5%D1%80', $this->getGenerator($routes)->generate('test', array('macedonian' => 'вечер')));

        $routes = $this->getRoutes('test', new Route('/ตอนเย็น/{thai}/{slug2}/{slug3}', array('thai' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/%E0%B8%95%E0%B8%AD%E0%B8%99%E0%B9%80%E0%B8%A2%E0%B9%87%E0%B8%99/%E0%B8%95%E0%B8%AD%E0%B8%99%E0%B9%80%E0%B8%A2%E0%B9%87%E0%B8%99', $this->getGenerator($routes)->generate('test', array('thai' => 'ตอนเย็น')));

        $routes = $this->getRoutes('test', new Route('/buổi tối/{vietnamese}/{slug2}/{slug3}', array('vietnamese' => null, 'slug2' => null, 'slug3' => null)));
        $this->assertEquals('/app.php/bu%E1%BB%95i%20t%E1%BB%91i/bu%E1%BB%95i%20t%E1%BB%91i', $this->getGenerator($routes)->generate('test', array('vietnamese' => 'buổi tối')));
    }

    public function testWithAnIntegerAsADefaultValue()
    {
        $routes = $this->getRoutes('test', new Route('/{default}', array('default' => 0)));

        $this->assertEquals('/app.php/foo', $this->getGenerator($routes)->generate('test', array('default' => 'foo')));
    }

    protected function getGenerator(RouteCollection $routes, array $parameters = array())
    {
        $context = new RequestContext('/app.php');
        foreach ($parameters as $key => $value) {
            $method = 'set'.$key;
            $context->$method($value);
        }
        $generator = new UrlGenerator($routes, $context);

        return $generator;
    }

    protected function getRoutes($name, Route $route)
    {
        $routes = new RouteCollection();
        $routes->add($name, $route);

        return $routes;
    }
}
