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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', GuardTokenInterface::class);

/**
 * A marker interface that both guard tokens implement.
 *
 * Any tokens passed to GuardAuthenticationProvider (i.e. any tokens that
 * are handled by the guard auth system) must implement this
 * interface.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
interface GuardTokenInterface extends TokenInterface
{
}
