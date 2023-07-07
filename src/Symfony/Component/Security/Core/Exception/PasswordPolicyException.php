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

final class PasswordPolicyException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'The password does not fulfill the password policy.';
    }
}
