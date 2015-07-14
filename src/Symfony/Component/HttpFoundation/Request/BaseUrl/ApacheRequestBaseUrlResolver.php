<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/7/15
 * Time: 10:55 PM
 */

namespace Symfony\Component\HttpFoundation\Request\BaseUrl;


use Symfony\Component\HttpFoundation\ApacheRequest;
use Symfony\Component\HttpFoundation\Request;

class ApacheRequestBaseUrlResolver implements BaseUrlResolverInterface
{
    public function resolveBaseUrl(Request $request)
    {
        if (!$request instanceof ApacheRequest) {
            return false;
        }

        $baseUrl = $request->server->get('SCRIPT_NAME');

        if (false === strpos($request->server->get('REQUEST_URI'), $baseUrl)) {
            // assume mod_rewrite
            return rtrim(dirname($baseUrl), '/\\');
        }

        return $baseUrl;
    }
}