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
 * Request uri resolver that takes into account how IIS with ASAPI Rewrite works
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class IISWithASAPIRewriteUriResolver implements UriResolverInterface
{
    public function resolveUri(Request $request)
    {
        if (!$request->headers->has('X_REWRITE_URL')) {
            return false;
        }

        // IIS with ISAPI_Rewrite
        return $request->headers->get('X_REWRITE_URL');
    }
}