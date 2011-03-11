<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * UsernameNotFoundException is thrown if a User cannot be found by its username.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UsernameNotFoundException extends AuthenticationException
{
}
