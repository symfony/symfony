<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpKernelExtension extends \Twig_Extension implements EventSubscriberInterface
{
    private $kernel;
    private $request;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $kernel A HttpKernelInterface install
     */
    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function getFunctions()
    {
        return array(
            'render' => new \Twig_Function_Method($this, 'render', array('needs_environment' => true, 'is_safe' => array('html'))),
        );
    }

    /**
     * Renders a URI.
     *
     * @param \Twig_Environment $twig A \Twig_Environment instance
     * @param string            $uri  The URI to render
     *
     * @return string The Response content
     */
    public function render(\Twig_Environment $twig, $uri)
    {
        if (null !== $this->request) {
            $cookies = $this->request->cookies->all();
            $server = $this->request->server->all();
        } else {
            $cookies = array();
            $server = array();
        }

        $subRequest = Request::create($uri, 'get', array(), $cookies, array(), $server);
        if (null !== $this->request && $this->request->getSession()) {
            $subRequest->setSession($this->request->getSession());
        }

        $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $response->getStatusCode()));
        }

        return $response->getContent();
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->request = $event->getRequest();
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest'),
        );
    }

    public function getName()
    {
        return 'http_kernel';
    }
}
