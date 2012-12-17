<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

use Symfony\Component\Security\Core\SecurityContextInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Encapsulates the logic needed to create sub-requests, redirect the user, and match URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpUtils
{
    private $router;

    /**
     * Constructor.
     *
     * @param RouterInterface $router An RouterInterface instance
     */
    public function __construct(RouterInterface $router = null)
    {
        $this->router = $router;
    }

    /**
     * Creates a redirect Response.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     * @param integer $status  The status code
     *
     * @return Response A RedirectResponse instance
     */
    public function createRedirectResponse(Request $request, $path, $status = 302)
    {
        if ('/' === $path[0]) {
            $path = $request->getUriForPath($path);
        } elseif (0 !== strpos($path, 'http')) {
            $this->resetLocale($request);
            $path = $this->generateUrl($path, true);
        }

        return new RedirectResponse($path, $status);
    }

    /**
     * Creates a Request.
     *
     * @param Request $request The current Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @return Request A Request instance
     */
    public function createRequest(Request $request, $path)
    {
        if ($path && '/' !== $path[0] && 0 !== strpos($path, 'http')) {
            $this->resetLocale($request);
            $path = $this->generateUrl($path, true);
        }
        if (0 !== strpos($path, 'http')) {
            $path = $request->getUriForPath($path);
        }

        $newRequest = Request::create($path, 'get', array(), $request->cookies->all(), array(), $request->server->all());
        if ($session = $request->getSession()) {
            $newRequest->setSession($session);
        }

        if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $newRequest->attributes->set(SecurityContextInterface::AUTHENTICATION_ERROR, $request->attributes->get(SecurityContextInterface::AUTHENTICATION_ERROR));
        }
        if ($request->attributes->has(SecurityContextInterface::ACCESS_DENIED_ERROR)) {
            $newRequest->attributes->set(SecurityContextInterface::ACCESS_DENIED_ERROR, $request->attributes->get(SecurityContextInterface::ACCESS_DENIED_ERROR));
        }
        if ($request->attributes->has(SecurityContextInterface::LAST_USERNAME)) {
            $newRequest->attributes->set(SecurityContextInterface::LAST_USERNAME, $request->attributes->get(SecurityContextInterface::LAST_USERNAME));
        }

        return $newRequest;
    }

    /**
     * Checks that a given path matches the Request.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo) or a route name (foo))
     *
     * @return Boolean true if the path is the same as the one from the Request, false otherwise
     */
    public function checkRequestPath(Request $request, $path)
    {
        if ('/' !== $path[0]) {
            try {
                $parameters = $this->router->match(urlencode($request->getPathInfo()));

                return $path === $parameters['_route'];
            } catch (MethodNotAllowedException $e) {
                return false;
            } catch (ResourceNotFoundException $e) {
                return false;
            }
        }

        return $path === $request->getPathInfo();
    }

    // hack (don't have a better solution for now)
    private function resetLocale(Request $request)
    {
        $context = $this->router->getContext();
        if ($context->getParameter('_locale')) {
            return;
        }

        try {
            $parameters = $this->router->match(urlencode($request->getPathInfo()));

            if (isset($parameters['_locale'])) {
                $context->setParameter('_locale', $parameters['_locale']);
            } elseif ($session = $request->getSession()) {
                $context->setParameter('_locale', $session->getLocale());
            }
        } catch (\Exception $e) {
            // let's hope user doesn't use the locale in the path
        }
    }

    private function generateUrl($route, $absolute = false)
    {
        if (null === $this->router) {
            throw new \LogicException('You must provide a RouterInterface instance to be able to use routes.');
        }

        return $this->router->generate($route, array(), $absolute);
    }
}
