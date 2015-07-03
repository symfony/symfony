<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 1:50 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class OrigPathInfoUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if (!$request->server->has('ORIG_PATH_INFO')) {
            return false;
        }

        // IIS 5.0, PHP as CGI
        $requestUri = $request->server->get('ORIG_PATH_INFO');
        if ('' != $request->server->get('QUERY_STRING')) {
            $requestUri .= '?'.$request->server->get('QUERY_STRING');
        }
        $request->server->remove('ORIG_PATH_INFO');
        return $requestUri;
    }
}