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

    public function testGenerateForRouteWithInvalidOptionalParameterNonStrict()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', array('foo' => '1'), array('foo' => 'd+')));
        $generator = $this->getGenerator($routes);
        $generator->setStrictRequirements(false);
        $this->assertNull($generator->generate('test', array('foo' => 'bar'), true));
    }

    public function testGenerateForRouteWithInvalidOptionalParameterNonStrictWithLogger()
    {
        if (!interface_exists('Symfony\Component\HttpKernel\Log\LoggerInterface')) {
            $this->markTestSkipped('The "HttpKernel" component is not available');
        }

        $routes = $this->getRoutes('test', new Route('/testing/{foo}', array('foo' => '1'), array('foo' => 'd+')));
        $logger = $this->getMock('Symfony\Component\HttpKernel\Log\LoggerInterface');
        $logger->expects($this->once())
            ->method('err');
        $generator = $this->getGenerator($routes, array(), $logger);
        $generator->setStrictRequirements(false);
        $this->assertNull($generator->generate('test', array('foo' => 'bar'), true));
    }

    /**
     * @expectedException Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function testGenerateForRouteWithInvalidMandatoryParameter()
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

    public function testWithAnIntegerAsADefaultValue()
    {
        $routes = $this->getRoutes('test', new Route('/{default}', array('default' => 0)));

        $this->assertEquals('/app.php/foo', $this->getGenerator($routes)->generate('test', array('default' => 'foo')));
    }

    public function testUrlEncoding()
    {
        // This tests the encoding of reserved characters that are used for delimiting of URI components (defined in RFC 3986)
        // and other special ASCII chars. These chars are tested as static text path, variable path and query param.
        $chars = '@:[]/()*\'" +,;-._~&$<>|{}%\\^`!?foo=bar#id';
        $routes = $this->getRoutes('test', new Route("/$chars/{varpath}", array(), array('varpath' => '.+')));
        $this->assertSame('/app.php/@:%5B%5D/%28%29*%27%22%20+,;-._~%26%24%3C%3E|%7B%7D%25%5C%5E%60!%3Ffoo=bar%23id'
            . '/@:%5B%5D/%28%29*%27%22%20+,;-._~%26%24%3C%3E|%7B%7D%25%5C%5E%60!%3Ffoo=bar%23id'
            . '?query=%40%3A%5B%5D%2F%28%29%2A%27%22+%2B%2C%3B-._%7E%26%24%3C%3E%7C%7B%7D%25%5C%5E%60%21%3Ffoo%3Dbar%23id',
            $this->getGenerator($routes)->generate('test', array(
                'varpath' => $chars,
                'query' => $chars
            ))
        );
    }

    public function testEncodingOfRelativePathSegments()
    {
        $routes = $this->getRoutes('test', new Route('/dir/../dir/..'));
        $this->assertSame('/app.php/dir/%2E%2E/dir/%2E%2E', $this->getGenerator($routes)->generate('test'));
        $routes = $this->getRoutes('test', new Route('/dir/./dir/.'));
        $this->assertSame('/app.php/dir/%2E/dir/%2E', $this->getGenerator($routes)->generate('test'));
        $routes = $this->getRoutes('test', new Route('/a./.a/a../..a/...'));
        $this->assertSame('/app.php/a./.a/a../..a/...', $this->getGenerator($routes)->generate('test'));
    }

    protected function getGenerator(RouteCollection $routes, array $parameters = array(), $logger = null)
    {
        $context = new RequestContext('/app.php');
        foreach ($parameters as $key => $value) {
            $method = 'set'.$key;
            $context->$method($value);
        }
        $generator = new UrlGenerator($routes, $context, $logger);

        return $generator;
    }

    protected function getRoutes($name, Route $route)
    {
        $routes = new RouteCollection();
        $routes->add($name, $route);

        return $routes;
    }
}
