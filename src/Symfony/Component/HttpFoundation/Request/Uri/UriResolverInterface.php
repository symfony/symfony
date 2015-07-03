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
 * This interface is for classes that are set up to resolve the request
 * uri based off a request instance
 *
 * @author Yosef Deray <yderay@gmail.com>
 */
interface UriResolverInterface
{
    public function resolveUri(Request $request);
}