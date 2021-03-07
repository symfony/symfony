<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', UserChecker::class, InMemoryUserChecker::class);

class_exists(InMemoryUserChecker::class);

if (false) {
    /**
     * UserChecker checks the user account flags.
     *
     * @author Fabien Potencier <fabien@symfony.com>
     *
     * @deprecated since Symfony 5.3, use {@link InMemoryUserChecker} instead
     */
    class UserChecker
    {
    }
}
