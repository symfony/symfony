<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RequestListener.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestListener
{
    protected $router;
    protected $logger;
    protected $container;

    public function __construct(ContainerInterface $container, RouterInterface $router, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->router = $router;
        $this->logger = $logger;
    }

    /**
     * Registers a core.request listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.request', array($this, 'handle'), $priority);
    }

    public function handle(Event $event)
    {
        $request = $event->get('request');
        $master = HttpKernelInterface::MASTER_REQUEST === $event->get('request_type');

        $this->initializeSession($request, $master);

        $this->initializeRequestAttributes($request, $master);
    }

    protected function initializeSession(Request $request, $master)
    {
        if (!$master) {
            return;
        }

        // inject the session object if none is present
        if (null === $request->getSession()) {
            $request->setSession($this->container->get('session'));
        }

        // starts the session if a session cookie already exists in the request...
        if ($request->hasSession()) {
            $request->getSession()->start();
        }
    }

    protected function initializeRequestAttributes(Request $request, $master)
    {
        if ($master) {
            // set the context even if the parsing does not need to be done
            // to have correct link generation
            $this->router->setContext(array(
                'base_url'  => $request->getBaseUrl(),
                'method'    => $request->getMethod(),
                'host'      => $request->getHost(),
                'is_secure' => $request->isSecure(),
            ));
        }

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        // add attributes based on the path info (routing)
        if (false !== $parameters = $this->router->match($request->getPathInfo())) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], str_replace("\n", '', var_export($parameters, true))));
            }

            $request->attributes->add($parameters);

            if ($locale = $request->attributes->get('_locale')) {
                $request->getSession()->setLocale($locale);
            }
        } elseif (null !== $this->logger) {
            $this->logger->err(sprintf('No route found for %s', $request->getPathInfo()));
        }
    }
}
