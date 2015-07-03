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
 * Request uri resolver that resolves the uri if ORIG_PATH_INFO is defined
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
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
        return $requestUri;
    }
}