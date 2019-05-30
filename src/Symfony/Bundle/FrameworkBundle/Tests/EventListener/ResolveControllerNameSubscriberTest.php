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

use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\EventListener\ResolveControllerNameSubscriber;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ResolveControllerNameSubscriberTest extends TestCase
{
    /**
     * @group legacy
     */
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
        $subscriber->onKernelRequest(new RequestEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
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
        $subscriber->onKernelRequest(new RequestEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals($controller, $request->attributes->get('_controller'));
    }

    public function provideSkippedControllers()
    {
        yield ['Other:format'];
        yield [function () {}];
    }
}
