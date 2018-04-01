<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Authentication;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SimplePreAuthenticatorInterface extends SimpleAuthenticatorInterface
{
    public function createToken(Request $request, $providerKey);
}
