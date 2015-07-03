<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 1:52 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\ApacheRequest;
use Symfony\Component\HttpFoundation\Request;

class ApacheRequestUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if (!$request instanceof ApacheRequest) {
            return false;
        }

        return $request->server->get('REQUEST_URI');
    }
}