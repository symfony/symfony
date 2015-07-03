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
        if (!$request->headers->has('X_ORIGINAL_URL')) {
            return false;
        }

        // IIS with Microsoft Rewrite Module
        return $request->headers->get('X_ORIGINAL_URL');
    }
}