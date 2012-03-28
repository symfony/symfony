<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RequestContext;

class RouterListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        if (!class_exists('Symfony\Component\Routing\Router')) {
            $this->markTestSkipped('The "Routing" component is not available');
        }
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $urlMatcher = $this->getMockBuilder('Symfony\Component\Routing\Matcher\UrlMatcherInterface')
                             ->disableOriginalConstructor()
                             ->getMock();
        $context = new RequestContext();
        $context->setHttpPort($defaultHttpPort);
        $context->setHttpsPort($defaultHttpsPort);
        $urlMatcher->expects($this->any())
                     ->method('getContext')
                     ->will($this->returnValue($context));

        $listener = new RouterListener($urlMatcher);
        $event = $this->createGetResponseEventForUri($uri);
        $listener->onKernelRequest($event);

        $this->assertEquals($expectedHttpPort, $context->getHttpPort());
        $this->assertEquals($expectedHttpsPort, $context->getHttpsPort());
        $this->assertEquals(0 === strpos($uri, 'https') ? 'https' : 'http', $context->getScheme());
    }

    public function getPortData()
    {
        return array(
            array(80, 443, 'http://localhost/', 80, 443),
            array(80, 443, 'http://localhost:90/', 90, 443),
            array(80, 443, 'https://localhost/', 80, 443),
            array(80, 443, 'https://localhost:90/', 80, 90),
        );
    }

    /**
     * @param string $uri
     *
     * @return GetResponseEvent
     */
    private function createGetResponseEventForUri($uri)
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $request = Request::create($uri);
        $request->attributes->set('_controller', null); // Prevents going in to routing process

        return new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    }
}
