<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Hasher;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

trait CheckPasswordLengthTrait
{
    private function isPasswordTooLong(string $password): bool
    {
        return PasswordHasherInterface::MAX_PASSWORD_LENGTH < \strlen($password);
    }
}
