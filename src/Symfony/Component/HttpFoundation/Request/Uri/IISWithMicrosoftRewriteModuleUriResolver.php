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
 * Request uri resolver that takes into account how IIS7 with
 * Microsoft Rewrite Module works
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
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