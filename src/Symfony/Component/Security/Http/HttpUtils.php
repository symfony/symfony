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

/**
 * Encapsulates the logic needed to create sub-requests, redirect the user, and match URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpUtils
{
    /**
     * @param $domainRegexp       A regexp the target of HTTP redirections must match, scheme included
     * @param $secureDomainRegexp A regexp the target of HTTP redirections must match when the scheme is "https"
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private ?UrlGeneratorInterface $urlGenerator = null,
        private UrlMatcherInterface|RequestMatcherInterface|null $urlMatcher = null,
        private ?string $domainRegexp = null,
        private ?string $secureDomainRegexp = null,
    ) {
    }

    /**
     * Creates a redirect Response.
     *
     * @param string $path   A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     * @param int    $status The HTTP status code (302 "Found" by default)
     */
    public function createRedirectResponse(Request $request, string $path, int $status = 302): RedirectResponse
    {
        if (null !== $this->secureDomainRegexp && 'https' === $this->urlMatcher->getContext()->getScheme() && preg_match('#^https?:[/\\\\]{2,}+[^/]++#i', $path, $host) && !preg_match(\sprintf($this->secureDomainRegexp, preg_quote($request->getHttpHost())), $host[0])) {
            $path = '/';
        }
        if (null !== $this->domainRegexp && preg_match('#^https?:[/\\\\]{2,}+[^/]++#i', $path, $host) && !preg_match(\sprintf($this->domainRegexp, preg_quote($request->getHttpHost())), $host[0])) {
            $path = '/';
        }

        return new RedirectResponse($this->generateUri($request, $path), $status);
    }

    /**
     * Creates a Request.
     *
     * @param string $path A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     */
    public function createRequest(Request $request, string $path): Request
    {
        if ($trustedProxies = Request::getTrustedProxies()) {
            Request::setTrustedProxies([], Request::getTrustedHeaderSet());
        }

        $context = $this->urlGenerator?->getContext();
        if ($baseUrl = $context?->getBaseUrl()) {
            $context->setBaseUrl('');
        }

        try {
            $newRequest = Request::create($this->generateUri($request, $path), 'get', [], $request->cookies->all(), [], $request->server->all());
        } finally {
            if ($trustedProxies) {
                Request::setTrustedProxies($trustedProxies, Request::getTrustedHeaderSet());
            }
            if ($baseUrl) {
                $context->setBaseUrl($baseUrl);
            }
        }

        static $setSession;

        $setSession ??= \Closure::bind(static function ($newRequest, $request) { $newRequest->session = $request->session; }, null, Request::class);
        $setSession($newRequest, $request);

        if ($request->attributes->has(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $newRequest->attributes->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $request->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
        }
        if ($request->attributes->has(SecurityRequestAttributes::ACCESS_DENIED_ERROR)) {
            $newRequest->attributes->set(SecurityRequestAttributes::ACCESS_DENIED_ERROR, $request->attributes->get(SecurityRequestAttributes::ACCESS_DENIED_ERROR));
        }
        if ($request->attributes->has(SecurityRequestAttributes::LAST_USERNAME)) {
            $newRequest->attributes->set(SecurityRequestAttributes::LAST_USERNAME, $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME));
        }

        if ($request->attributes->has('_format')) {
            $newRequest->attributes->set('_format', $request->attributes->get('_format'));
        }
        if ($request->getDefaultLocale() !== $request->getLocale()) {
            $newRequest->setLocale($request->getLocale());
        }

        return $newRequest;
    }

    /**
     * Checks that a given path matches the Request.
     *
     * @param string $path A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @return bool true if the path is the same as the one from the Request, false otherwise
     */
    public function checkRequestPath(Request $request, string $path): bool
    {
        if ('/' !== $path[0]) {
            // Shortcut if request has already been matched before
            if ($request->attributes->has('_route')) {
                return $path === $request->attributes->get('_route');
            }

            try {
                // matching a request is more powerful than matching a URL path + context, so try that first
                if ($this->urlMatcher instanceof RequestMatcherInterface) {
                    $parameters = $this->urlMatcher->matchRequest($request);
                } else {
                    $parameters = $this->urlMatcher->match($request->getPathInfo());
                }

                return isset($parameters['_route']) && $path === $parameters['_route'];
            } catch (MethodNotAllowedException|ResourceNotFoundException) {
                return false;
            }
        }

        return $path === rawurldecode($request->getPathInfo());
    }

    /**
     * Generates a URI, based on the given path or absolute URL.
     *
     * @param string $path A path (an absolute path (/foo), an absolute URL (http://...), or a route name (foo))
     *
     * @throws \LogicException
     */
    public function generateUri(Request $request, string $path): string
    {
        $url = parse_url($path);

        if ('' === $path || isset($url['scheme'], $url['host'])) {
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
            $fragment = parse_url($url, \PHP_URL_FRAGMENT);
            $url = substr($url, 0, $position);
            // fragment must be preserved
            if ($fragment) {
                $url .= "#$fragment";
            }
        }

        return $url;
    }
}
