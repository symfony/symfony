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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Encapsulates the logic needed to create sub-requests, redirect the user, and match URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpUtils
{
    private $urlGenerator;
    private $urlMatcher;
    private $domainRegexp;

    /**
     * @param UrlGeneratorInterface                       $urlGenerator A UrlGeneratorInterface instance
     * @param UrlMatcherInterface|RequestMatcherInterface $urlMatcher   The URL or Request matcher
     * @param string|null                                 $domainRegexp A regexp that the target of HTTP redirections must match, scheme included
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UrlGeneratorInterface $urlGenerator = null, $urlMatcher = null, $domainRegexp = null)
    {
        $this->urlGenerator = $urlGenerator;
        if (null !== $urlMatcher && !$urlMatcher instanceof UrlMatcherInterface && !$urlMatcher instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
        }
        $this->urlMatcher = $urlMatcher;
        $this->domainRegexp = $domainRegexp;
    }

    /**
     * Creates a redirect Response.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     * @param int     $status  The status code
     *
     * @return RedirectResponse A RedirectResponse instance
     */
    public function createRedirectResponse(Request $request, $path, $status = 302)
    {
        if (null !== $this->domainRegexp && preg_match('#^https?://[^/]++#i', $path, $host) && !preg_match(sprintf($this->domainRegexp, preg_quote($request->getHttpHost())), $host[0])) {
            $path = '/';
        }

        return new RedirectResponse($this->generateUri($request, $path), $status);
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
        $newRequest = Request::create($this->generateUri($request, $path), 'get', array(), $request->cookies->all(), array(), $request->server->all());

        static $setSession;

        if (null === $setSession) {
            $setSession = \Closure::bind(function ($newRequest, $request) { $newRequest->session = $request->session; }, null, Request::class);
        }
        $setSession($newRequest, $request);

        if ($request->attributes->has(Security::AUTHENTICATION_ERROR)) {
            $newRequest->attributes->set(Security::AUTHENTICATION_ERROR, $request->attributes->get(Security::AUTHENTICATION_ERROR));
        }
        if ($request->attributes->has(Security::ACCESS_DENIED_ERROR)) {
            $newRequest->attributes->set(Security::ACCESS_DENIED_ERROR, $request->attributes->get(Security::ACCESS_DENIED_ERROR));
        }
        if ($request->attributes->has(Security::LAST_USERNAME)) {
            $newRequest->attributes->set(Security::LAST_USERNAME, $request->attributes->get(Security::LAST_USERNAME));
        }

        return $newRequest;
    }

    /**
     * Checks that a given path matches the Request.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @return bool true if the path is the same as the one from the Request, false otherwise
     */
    public function checkRequestPath(Request $request, $path)
    {
        if ('/' !== $path[0]) {
            try {
                // matching a request is more powerful than matching a URL path + context, so try that first
                if ($this->urlMatcher instanceof RequestMatcherInterface) {
                    $parameters = $this->urlMatcher->matchRequest($request);
                } else {
                    $parameters = $this->urlMatcher->match($request->getPathInfo());
                }

                return isset($parameters['_route']) && $path === $parameters['_route'];
            } catch (MethodNotAllowedException $e) {
                return false;
            } catch (ResourceNotFoundException $e) {
                return false;
            }
        }

        return $path === rawurldecode($request->getPathInfo());
    }

    /**
     * Generates a URI, based on the given path or absolute URL.
     *
     * @param Request $request A Request instance
     * @param string  $path    A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @return string An absolute URL
     *
     * @throws \LogicException
     */
    public function generateUri($request, $path)
    {
        if (0 === strpos($path, 'http') || !$path) {
            return $path;
        }

        if ('/' === $path[0]) {
            return $request->getUriForPath($path);
        }

        if (null === $this->urlGenerator) {
            throw new \LogicException('You must provide a UrlGeneratorInterface instance to be able to use routes.');
        }

        $url = $this->urlGenerator->generate($path, $request->attributes->all(), UrlGeneratorInterface::ABSOLUTE_URL);

        // unnecessary query string parameters must be removed from URL
        // (ie. query parameters that are presents in $attributes)
        // fortunately, they all are, so we have to remove entire query string
        $position = strpos($url, '?');
        if (false !== $position) {
            $fragment = parse_url($url, PHP_URL_FRAGMENT);
            $url = substr($url, 0, $position);
            // fragment must be preserved
            if ($fragment) {
                $url .= "#$fragment";
            }
        }

        return $url;
    }
}
