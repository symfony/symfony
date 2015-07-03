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
 * Request uri resolver that takes into account how IIS7 with URL Rewrite works
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
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