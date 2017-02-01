<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\EventListener;

use Symfony\Component\Asset\EventListener\PreloadListener;
use Symfony\Component\Asset\Preload\HttpFoundationPreloadManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testOnKernelResponse()
    {
        $manager = new HttpFoundationPreloadManager();
        $manager->addResource('/foo');

        $subscriber = new PreloadListener($manager);
        $response = new Response();

        $event = $this->getMockBuilder(FilterResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('getResponse')->willReturn($response);

        $subscriber->onKernelResponse($event);

        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);
        $this->assertEquals('</foo>; rel=preload', $response->headers->get('Link'));
        $this->assertEmpty($manager->getResources());
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals(array(KernelEvents::RESPONSE => 'onKernelResponse'), PreloadListener::getSubscribedEvents());
    }
}
