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

/**
 * A marker interface that both guard tokens implement.
 *
 * Any tokens passed to GuardAuthenticationProvider (i.e. any tokens that
 * are handled by the guard auth system) must implement this
 * interface.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface GuardTokenInterface
{
}
