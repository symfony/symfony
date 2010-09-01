<?php

namespace Symfony\Bundle\WebProfilerBundle;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * WebDebugToolbarListener injects the Web Debug Toolbar.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebDebugToolbarListener
{
    protected $resolver;
    protected $interceptRedirects;

    public function __construct(ControllerResolver $resolver, $interceptRedirects = false)
    {
        $this->resolver = $resolver;
        $this->interceptRedirects = $interceptRedirects;
    }

    /**
     * Registers a core.response listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.response', array($this, 'handle'), $priority);
    }

    public function handle(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return $response;
        }

        if ($response->headers->has('X-Debug-Token') && $response->isRedirect() && $this->interceptRedirects) {
            $r = new Response();
            $r->setContent(
                sprintf('<html><head></head><body><h1>This Request redirects to<br /><a href="%s">%s</a>.</h1></body></html>',
                $response->headers->get('location'), $response->headers->get('location'))
            );
            $r->headers->set('X-Debug-Token', $response->headers->get('X-Debug-Token'));

            $response = $r;
        }

        $request = $event->getParameter('request');
        if (!$response->headers->has('X-Debug-Token')
            || '3' === substr($response->getStatusCode(), 0, 1)
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || $request->isXmlHttpRequest()
        ) {
            return $response;
        }

        $response->setContent($this->injectToolbar($request, $response));

        return $response;
    }

    /**
     * Injects the web debug toolbar into a given HTML string.
     *
     * @param string $content The HTML content
     *
     * @return Response A Response instance
     */
    protected function injectToolbar(Request $request, Response $response)
    {
        $toolbar = "\n".str_replace("\n", '', $this->resolver->render('WebProfilerBundle:Profiler:toolbar'))."\n";
        $content = str_ireplace('</body>', $toolbar.'</body>', $response->getContent(), $count);
        if (!$count) {
            $content .= $toolbar;
        }

        return $content;
    }
}
