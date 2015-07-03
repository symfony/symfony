<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/1/15
 * Time: 12:40 AM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class IISWithASAPIRewriteUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if (!$request->headers->has('X_REWRITE_URL')) {
            return false;
        }

        // IIS with ISAPI_Rewrite
        $requestUri = $request->headers->get('X_REWRITE_URL');
        $request->headers->remove('X_REWRITE_URL');
        return $requestUri;
    }
}