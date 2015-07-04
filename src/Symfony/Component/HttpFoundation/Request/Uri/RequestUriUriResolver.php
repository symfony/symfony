<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request uri resolver that resolves the uri if REQUEST_URI is defined.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class RequestUriUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if (!$request->server->has('REQUEST_URI')) {
            return false;
        }

        $requestUri = $request->server->get('REQUEST_URI');
        // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path, only use URL path
        $schemeAndHttpHost = $request->getSchemeAndHttpHost();
        if (strpos($requestUri, $schemeAndHttpHost) === 0) {
            $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
        }

        return $requestUri;
    }
}