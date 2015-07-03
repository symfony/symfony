<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/1/15
 * Time: 12:37 AM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;

use Symfony\Component\HttpFoundation\Request;

interface UriResolverInterface
{
    public function resolveUri(Request $request);
}