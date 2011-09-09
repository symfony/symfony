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
    private $context;

    protected function setUp()
    {
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\Router')
                             ->disableOriginalConstructor()
                             ->getMock();
        $this->context = new RequestContext();
        $this->router->expects($this->any())
                     ->method('getContext')
                     ->will($this->returnValue($this->context));
    }

    /**
     * @dataProvider getPortData
     */
    public function testPort($defaultHttpPort, $defaultHttpsPort, $uri, $expectedHttpPort, $expectedHttpsPort)
    {
        $listener = new RouterListener($this->router, $defaultHttpPort, $defaultHttpsPort);
        $event = $this->createGetResponseEventForUri($uri);
        $listener->onEarlyKernelRequest($event);

        $this->assertEquals($expectedHttpPort, $this->context->getHttpPort());
        $this->assertEquals($expectedHttpsPort, $this->context->getHttpsPort());
        $this->assertEquals(0 === strpos($uri, 'https') ? 'https' : 'http', $this->context->getScheme());
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
