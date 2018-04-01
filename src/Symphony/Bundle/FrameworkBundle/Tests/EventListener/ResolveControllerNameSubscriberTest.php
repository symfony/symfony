<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\EventListener;

use Symphony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symphony\Bundle\FrameworkBundle\EventListener\ResolveControllerNameSubscriber;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\HttpKernelInterface;

class ResolveControllerNameSubscriberTest extends TestCase
{
    public function testReplacesControllerAttribute()
    {
        $parser = $this->getMockBuilder(ControllerNameParser::class)->disableOriginalConstructor()->getMock();
        $parser->expects($this->any())
            ->method('parse')
            ->with('AppBundle:Starting:format')
            ->willReturn('App\\Final\\Format::methodName');
        $httpKernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();

        $request = new Request();
        $request->attributes->set('_controller', 'AppBundle:Starting:format');

        $subscriber = new ResolveControllerNameSubscriber($parser);
        $subscriber->onKernelRequest(new GetResponseEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals('App\\Final\\Format::methodName', $request->attributes->get('_controller'));
    }

    /**
     * @dataProvider provideSkippedControllers
     */
    public function testSkipsOtherControllerFormats($controller)
    {
        $parser = $this->getMockBuilder(ControllerNameParser::class)->disableOriginalConstructor()->getMock();
        $parser->expects($this->never())
            ->method('parse');
        $httpKernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();

        $request = new Request();
        $request->attributes->set('_controller', $controller);

        $subscriber = new ResolveControllerNameSubscriber($parser);
        $subscriber->onKernelRequest(new GetResponseEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals($controller, $request->attributes->get('_controller'));
    }

    public function provideSkippedControllers()
    {
        yield array('Other:format');
        yield array(function () {});
    }
}
