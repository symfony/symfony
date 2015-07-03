<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 1:18 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

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