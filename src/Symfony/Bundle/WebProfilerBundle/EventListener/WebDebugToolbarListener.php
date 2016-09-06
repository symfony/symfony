<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * WebDebugToolbarListener injects the Web Debug Toolbar.
 *
 * The onKernelResponse method must be connected to the kernel.response event.
 *
 * The WDT is only injected on well-formed HTML (with a proper </body> tag).
 * This means that the WDT is never included in sub-requests or ESI requests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WebDebugToolbarListener implements EventSubscriberInterface
{
    const DISABLED = 1;
    const ENABLED = 2;

    protected $twig;
    protected $urlGenerator;
    protected $interceptRedirects;
    protected $mode;
    protected $position;
    protected $excludedAjaxPaths;

    public function __construct(\Twig_Environment $twig, $interceptRedirects = false, $mode = self::ENABLED, $position = 'bottom', UrlGeneratorInterface $urlGenerator = null, $excludedAjaxPaths = '^/bundles|^/_wdt')
    {
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;
        $this->interceptRedirects = (bool) $interceptRedirects;
        $this->mode = (int) $mode;
        $this->position = $position;
        $this->excludedAjaxPaths = $excludedAjaxPaths;
    }

    public function isEnabled()
    {
        return self::DISABLED !== $this->mode;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        if ($response->headers->has('X-Debug-Token') && null !== $this->urlGenerator) {
            try {
                $response->headers->set(
                    'X-Debug-Token-Link',
                    $this->urlGenerator->generate('_profiler', array('token' => $response->headers->get('X-Debug-Token')), UrlGeneratorInterface::ABSOLUTE_URL)
                );
            } catch (\Exception $e) {
                $response->headers->set('X-Debug-Error', get_class($e).': '.$e->getMessage());
            }
        }

        if (!$event->isMasterRequest()) {
            return;
        }

        // do not capture redirects or modify XML HTTP Requests
        if ($request->isXmlHttpRequest()) {
            return;
        }

        if ($response->headers->has('X-Debug-Token') && $response->isRedirect() && $this->interceptRedirects) {
            $session = $request->getSession();
            if (null !== $session && $session->isStarted() && $session->getFlashBag() instanceof AutoExpireFlashBag) {
                // keep current flashes for one more request if using AutoExpireFlashBag
                $session->getFlashBag()->setAll($session->getFlashBag()->peekAll());
            }

            $response->setContent($this->twig->render('@WebProfiler/Profiler/toolbar_redirect.html.twig', array('location' => $response->headers->get('Location'))));
            $response->setStatusCode(200);
            $response->headers->remove('Location');
        }

        if (self::DISABLED === $this->mode
            || !$response->headers->has('X-Debug-Token')
            || $response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || false !== stripos($response->headers->get('Content-Disposition'), 'attachment;')
        ) {
            return;
        }

        $this->injectToolbar($response, $request);
    }

    /**
     * Injects the web debug toolbar into the given Response.
     */
    protected function injectToolbar(Response $response, Request $request)
    {
        $content = $response->getContent();
        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $toolbar = "\n".str_replace("\n", '', $this->twig->render(
                '@WebProfiler/Profiler/toolbar_js.html.twig',
                array(
                    'position' => $this->position,
                    'excluded_ajax_paths' => $this->excludedAjaxPaths,
                    'token' => $response->headers->get('X-Debug-Token'),
                    'request' => $request,
                )
            ))."\n";
            $content = substr($content, 0, $pos).$toolbar.substr($content, $pos);
            $response->setContent($content);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }
}
