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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\EventListener\PreloadListener;
use Symfony\Component\Asset\Preload\PreloadManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PreloadListenerTest extends TestCase
{
    public function testOnKernelResponse()
    {
        $manager = new PreloadManager();
        $manager->addResource('/foo');

        $subscriber = new PreloadListener($manager);
        $response = new Response('', 200, array('Link' => '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"'));

        $event = $this->getMockBuilder(FilterResponseEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('isMasterRequest')->willReturn(true);
        $event->method('getResponse')->willReturn($response);

        $subscriber->onKernelResponse($event);

        $this->assertInstanceOf(EventSubscriberInterface::class, $subscriber);

        $expected = array(
            '<https://demo.api-platform.com/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"',
            '</foo>; rel=preload',
        );

        $this->assertEquals($expected, $response->headers->get('Link', null, false));
        $this->assertNull($manager->buildLinkValue());
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals(array(KernelEvents::RESPONSE => 'onKernelResponse'), PreloadListener::getSubscribedEvents());
    }
}
