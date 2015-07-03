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

use Symfony\Component\HttpFoundation\ApacheRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request uri resolver that checks to see if the request is an instance of
 * an apache request which has been mostly rewritten by mod rewrite already
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
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