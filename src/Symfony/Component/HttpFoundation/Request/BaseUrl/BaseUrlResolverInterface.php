<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Request\BaseUrl;

use Symfony\Component\HttpFoundation\Request;

/**
 * interface for classes that can resolve the base url from a class
 *
 * @author Yosef Deray <yderay@gmail.com>
 */
interface BaseUrlResolverInterface
{
    public function resolveBaseUrl(Request $request);
}