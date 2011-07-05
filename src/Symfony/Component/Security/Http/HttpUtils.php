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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

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
        if (0 === strpos($path, '/')) {
            $path = $request->getUriForPath($path);
        } elseif (0 !== strpos($path, 'http')) {
            $path = $this->generateUrl($path, true);
        }

        return new RedirectResponse($path, 302);
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
            $path = $this->generateUrl($path, true);
        }

        return Request::create($path, 'get', array(), $request->cookies->all(), array(), $request->server->all());
    }

    /**
     * Checks that a given path matches the Request.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @return Boolean true if the path is the same as the one from the Request, false otherwise
     */
    public function checkRequestPath(Request $request, $path)
    {
        if ('/' !== $path[0]) {
            try {
                $parameters = $this->router->match($request->getPathInfo());

                return $path === $parameters['_route'];
            } catch (\Exception $e) {
            }
        }

        return $path === $request->getPathInfo();
    }

    private function generateUrl($route, $absolute = false)
    {
        if (null === $this->router) {
            throw new \LogicException('You must provide a RouterInterface instance to be able to use routes.');
        }

        return $this->router->generate($route, array(), $absolute);
    }
}
