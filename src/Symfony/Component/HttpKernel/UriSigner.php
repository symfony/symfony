<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;

/**
 * Signs URIs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UriSigner
{
    private $secret;
    private $parameter;

    /**
     * @param string $secret    A secret
     * @param string $parameter Query string parameter to use
     */
    public function __construct(string $secret, string $parameter = '_hash')
    {
        $this->secret = $secret;
        $this->parameter = $parameter;
    }

    /**
     * Signs a URI.
     *
     * The given URI is signed by adding the query string parameter
     * which value depends on the URI and the secret.
     *
     * @return string The signed URI
     */
    public function sign(string $uri)
    {
        $url = parse_url($uri);
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        } else {
            $params = [];
        }

        $uri = $this->buildUrl($url, $params);
        $params[$this->parameter] = $this->computeHash($uri);

        return $this->buildUrl($url, $params);
    }

    /**
     * Checks that a URI contains the correct hash.
     *
     * @return bool True if the URI is signed correctly, false otherwise
     */
    public function check(string $uri)
    {
        $url = parse_url($uri);
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        } else {
            $params = [];
        }

        if (empty($params[$this->parameter])) {
            return false;
        }

        $hash = $params[$this->parameter];
        unset($params[$this->parameter]);

        return hash_equals($this->computeHash($this->buildUrl($url, $params)), $hash);
    }

    public function checkRequest(Request $request): bool
    {
        $qs = ($qs = $request->server->get('QUERY_STRING')) ? '?'.$qs : '';

        // we cannot use $request->getUri() here as we want to work with the original URI (no query string reordering)
        return $this->check($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().$qs);
    }

    private function computeHash(string $uri): string
    {
        return base64_encode(hash_hmac('sha256', $uri, $this->secret, true));
    }

    private function buildUrl(array $url, array $params = []): string
    {
        ksort($params, SORT_STRING);
        $url['query'] = http_build_query($params, '', '&');

        $scheme = isset($url['scheme']) ? $url['scheme'].'://' : '';
        $host = isset($url['host']) ? $url['host'] : '';
        $port = isset($url['port']) ? ':'.$url['port'] : '';
        $user = isset($url['user']) ? $url['user'] : '';
        $pass = isset($url['pass']) ? ':'.$url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($url['path']) ? $url['path'] : '';
        $query = isset($url['query']) && $url['query'] ? '?'.$url['query'] : '';
        $fragment = isset($url['fragment']) ? '#'.$url['fragment'] : '';

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
    }
}
