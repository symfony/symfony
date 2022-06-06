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

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', UsernameNotFoundException::class, UserNotFoundException::class);

class_exists(UserNotFoundException::class);

if (false) {
    /**
     * @deprecated since Symfony 5.3 to be removed in 6.0, use UserNotFoundException instead.
     */
    class UsernameNotFoundException extends AuthenticationException
    {
    }
}
