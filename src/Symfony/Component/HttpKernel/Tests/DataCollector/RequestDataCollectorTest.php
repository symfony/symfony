<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class RequestDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    /**
     * @dataProvider provider
     */
    public function testCollect(Request $request, Response $response)
    {
        $c = new RequestDataCollector();

        $c->collect($request, $response);

        $this->assertSame('request',$c->getName());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\HeaderBag',$c->getRequestHeaders());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag',$c->getRequestServer());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag',$c->getRequestCookies());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag',$c->getRequestAttributes());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag',$c->getRequestRequest());
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\ParameterBag',$c->getRequestQuery());
        $this->assertEquals('html',$c->getFormat());
        $this->assertEquals(array(),$c->getSessionAttributes());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\HeaderBag',$c->getResponseHeaders());
        $this->assertEquals(200,$c->getStatusCode());
        $this->assertEquals('application/json',$c->getContentType());
    }

    public function provider()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            return array(array(null, null));
        }

        $request = Request::create('http://test.com/foo?bar=baz');
        $request->attributes->set('foo', 'bar');

        $response = new Response();
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->setCookie(new Cookie('foo','bar',1,'/foo','localhost',true,true));
        $response->headers->setCookie(new Cookie('bar','foo',new \DateTime('@946684800')));
        $response->headers->setCookie(new Cookie('bazz','foo','2000-12-12'));

        return array(
            array($request, $response)
        );
    }

}
