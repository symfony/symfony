<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * RequestListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

    public function onCoreRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $master = HttpKernelInterface::MASTER_REQUEST === $event->getRequestType();

        $this->initializeSession($request, $master);

        $this->initializeRequestAttributes($request, $master);
    }

    protected function initializeSession(Request $request, $master)
    {
        if (!$master) {
            return;
        }

        // inject the session object if none is present
        if (null === $request->getSession() && $this->container->has('session')) {
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
                'port'      => $request->getPort(),
                'is_secure' => $request->isSecure(),
            ));
        }

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        // add attributes based on the path info (routing)
        try {
            $parameters = $this->router->match($request->getPathInfo());

            if (null !== $this->logger) {
                $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], $this->parametersToString($parameters)));
            }

            $request->attributes->add($parameters);

            if ($locale = $request->attributes->get('_locale')) {
                $request->getSession()->setLocale($locale);
            }
        } catch (NotFoundException $e) {
            $message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());
            if (null !== $this->logger) {
                $this->logger->err($message);
            }
            throw new NotFoundHttpException($message, $e);
        } catch (MethodNotAllowedException $e) {
            $message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), strtoupper(implode(', ', $e->getAllowedMethods())));
            if (null !== $this->logger) {
                $this->logger->err($message);
            }
            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
        }
    }

    private function parametersToString(array $parameters)
    {
        $pieces = array();
        foreach ($parameters as $key => $val) {
            $pieces[] = sprintf('"%s": "%s"', $key, (is_string($val) ? $val : json_encode($val)));
        }

        return implode(', ', $pieces);
    }
}
