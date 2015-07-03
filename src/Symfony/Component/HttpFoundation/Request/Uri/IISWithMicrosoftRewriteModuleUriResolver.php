<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/1/15
 * Time: 12:40 AM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class IISWithMicrosoftRewriteModuleUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        $requestUri = false;

        if ($request->headers->has('X_ORIGINAL_URL')) {
            // IIS with Microsoft Rewrite Module
            $requestUri = $request->headers->get('X_ORIGINAL_URL');
            $request->headers->remove('X_ORIGINAL_URL');
            $request->server->remove('HTTP_X_ORIGINAL_URL');
            $request->server->remove('UNENCODED_URL');
            $request->server->remove('IIS_WasUrlRewritten');
        }

        return $requestUri;
    }
}