<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Guard\Token;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * A marker interface that both guard tokens implement.
 *
 * Any tokens passed to GuardAuthenticationProvider (i.e. any tokens that
 * are handled by the guard auth system) must implement this
 * interface.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface GuardTokenInterface extends TokenInterface
{
}
