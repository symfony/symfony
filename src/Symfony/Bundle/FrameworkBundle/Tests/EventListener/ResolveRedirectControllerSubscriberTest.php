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

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\EventListener\ResolveRedirectControllerSubscriber;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ResolveRedirectControllerSubscriberTest extends TestCase
{
    /**
     * @dataProvider provideRedirectionExamples
     */
    public function testSetControllerForRedirectToRoute(Request $request, array $expectedAttributes)
    {
        $httpKernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $subscriber = new ResolveRedirectControllerSubscriber();
        $subscriber->onKernelRequest(new GetResponseEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST));

        foreach ($expectedAttributes as $name => $value) {
            $this->assertEquals($value, $request->attributes->get($name));
        }
    }

    public function provideRedirectionExamples()
    {
        // No redirection
        yield array($this->requestWithAttributes(array(
            '_controller' => 'AppBundle:Starting:format',
        )), array(
            '_controller' => 'AppBundle:Starting:format',
        ));

        // Controller win over redirection
        yield array($this->requestWithAttributes(array(
            '_controller' => 'AppBundle:Starting:format',
            '_redirect_to' => 'https://google.com',
        )), array(
            '_controller' => 'AppBundle:Starting:format',
        ));

        // Redirection to URL
        yield array($this->requestWithAttributes(array(
            '_redirect_to' => 'https://google.com',
        )), array(
            '_controller' => array(RedirectController::class, 'urlRedirectAction'),
            'path' => 'https://google.com',
        ));

        // Redirection to route
        yield array($this->requestWithAttributes(array(
            '_redirect_to' => 'route',
        )), array(
            '_controller' => array(RedirectController::class, 'redirectAction'),
            'route' => 'route',
        ));

        // Permanent redirection to route
        yield array($this->requestWithAttributes(array(
            '_redirect_to' => 'route',
            '_redirect_permanent' => true,
        )), array(
            '_controller' => array(RedirectController::class, 'redirectAction'),
            'route' => 'route',
            'permanent' => true,
        ));
    }

    private function requestWithAttributes(array $attributes): Request
    {
        $request = new Request();

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }
}
