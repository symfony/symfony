<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\EventListener\RouterListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RequestContext;

class RouterListenerTest extends \PHPUnit_Framework_TestCase
{
    private $router;

    protected function setUp()
    {
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')
                             ->disableOriginalConstructor()
                             ->getMock();
    }

    protected function tearDown()
    {
        $this->router = null;
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $listener = new RouterListener($this->router, $defaultHttpPort, $defaultHttpsPort);

        $expectedContext = new RequestContext();
        $expectedContext->setHttpPort($expectedHttpPort);
        $expectedContext->setHttpsPort($expectedHttpsPort);
        $expectedContext->setScheme(0 === strpos($uri, 'https') ? 'https' : 'http');
        $this->router->expects($this->once())
                     ->method('setContext')
                     ->with($expectedContext);

        $event = $this->createGetResponseEventForUri($uri);
        $listener->onEarlyKernelRequest($event);
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
