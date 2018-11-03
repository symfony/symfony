<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @deprecated since Symfony 4.2, use Guard instead.
 */
interface SimpleFormAuthenticatorInterface extends SimpleAuthenticatorInterface
{
    public function createToken(Request $request, $username, $password, $providerKey);
}
