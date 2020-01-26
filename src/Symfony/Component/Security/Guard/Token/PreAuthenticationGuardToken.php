<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Token;

use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticationGuardToken as CorePreAuthenticationGuardToken;

/**
 * The token used by the guard auth system before authentication.
 *
 * The GuardAuthenticationListener creates this, which is then consumed
 * immediately by the GuardAuthenticationProvider. If authentication is
 * successful, a different authenticated token is returned
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class PreAuthenticationGuardToken extends CorePreAuthenticationGuardToken implements GuardTokenInterface
{
}
