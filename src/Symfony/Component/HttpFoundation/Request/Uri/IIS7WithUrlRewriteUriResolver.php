<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 1:15 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class IIS7WithUrlRewriteUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if ($request->server->get('IIS_WasUrlRewritten') != '1' || $request->server->get('UNENCODED_URL') == '') {
            return false;
        }

        // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
        return $request->server->get('UNENCODED_URL');
    }
}