<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * WebDebugToolbarListener injects the Web Debug Toolbar.
 *
 * The handle method must be connected to the core.response event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class WebDebugToolbarListener
{
    protected $kernel;
    protected $interceptRedirects;

    public function __construct(HttpKernel $kernel, $interceptRedirects = false)
    {
        $this->kernel = $kernel;
        $this->interceptRedirects = $interceptRedirects;
    }

    public function handle(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if ($response->headers->has('X-Debug-Token') && $response->isRedirect() && $this->interceptRedirects) {
            $response->setContent(
                sprintf('<html><head></head><body><h1>This Request redirects to<br /><a href="%1$s">%1$s</a>.</h1><h4>The redirect was intercepted by the web debug toolbar to help debugging.<br/>For more information, see the "intercept-redirects" option of the Profiler.</h4></body></html>',
                $response->headers->get('Location'))
            );
            $response->setStatusCode(200);
            $response->headers->remove('Location');
        }

        $request = $event->get('request');
        if (!$response->headers->has('X-Debug-Token')
            || '3' === substr($response->getStatusCode(), 0, 1)
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || $request->isXmlHttpRequest()
        ) {
            return $response;
        }

        $this->injectToolbar($request, $response);

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
        if (function_exists('mb_stripos')) {
            $posrFunction = 'mb_strripos';
            $substrFunction = 'mb_substr';
        } else {
            $posrFunction = 'strripos';
            $substrFunction = 'substr';
        }

        $toolbar = "\n".str_replace("\n", '', $this->kernel->render('WebProfilerBundle:Profiler:toolbar'))."\n";
        $content = $response->getContent();

        if (false === $pos = $posrFunction($content, '</body>')) {
            $content .= $toolbar;
        } else {
            $content = $substrFunction($content, 0, $pos).$toolbar.$substrFunction($content, $pos);
        }

        $response->setContent($content);
    }
}
