<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http;

use Symphony\Component\HttpFoundation\Request;

/**
 * AccessMap allows configuration of different access control rules for
 * specific parts of the website.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Kris Wallsmith <kris@symphony.com>
 */
interface AccessMapInterface
{
    /**
     * Returns security attributes and required channel for the supplied request.
     *
     * @return array A tuple of security attributes and the required channel
     */
    public function getPatterns(Request $request);
}
