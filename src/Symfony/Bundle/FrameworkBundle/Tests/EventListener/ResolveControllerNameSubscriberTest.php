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
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @group legacy
 */
class ResolveControllerNameSubscriberTest extends TestCase
{
    public function testReplacesControllerAttribute()
    {
        $parser = $this->createMock(ControllerNameParser::class);
        $parser->expects($this->any())
            ->method('parse')
            ->with('AppBundle:Starting:format')
            ->willReturn('App\\Final\\Format::methodName');
        $httpKernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_controller', 'AppBundle:Starting:format');

        $subscriber = new ResolveControllerNameSubscriber($parser);
        $subscriber->onKernelRequest(new RequestEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals('App\\Final\\Format::methodName', $request->attributes->get('_controller'));

        $subscriber = new ChildResolveControllerNameSubscriber($parser);
        $subscriber->onKernelRequest(new RequestEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals('App\\Final\\Format::methodName', $request->attributes->get('_controller'));
    }

    /**
     * @dataProvider provideSkippedControllers
     */
    public function testSkipsOtherControllerFormats($controller)
    {
        $parser = $this->createMock(ControllerNameParser::class);
        $parser->expects($this->never())
            ->method('parse');
        $httpKernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->attributes->set('_controller', $controller);

        $subscriber = new ResolveControllerNameSubscriber($parser);
        $subscriber->onKernelRequest(new RequestEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));
        $this->assertEquals($controller, $request->attributes->get('_controller'));
    }

    public static function provideSkippedControllers()
    {
        yield ['Other:format'];
        yield [function () {}];
    }
}

class ChildResolveControllerNameSubscriber extends ResolveControllerNameSubscriber
{
    public function onKernelRequest(GetResponseEvent $event)
    {
        parent::onKernelRequest($event);
    }
}
