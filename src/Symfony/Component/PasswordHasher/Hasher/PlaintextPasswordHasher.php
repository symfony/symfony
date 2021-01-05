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

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

/**
 * PlaintextPasswordHasher does not do any hashing but is useful in testing environments.
 *
 * As this hasher is not cryptographically secure, usage of it in production environments is discouraged.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PlaintextPasswordHasher implements LegacyPasswordHasherInterface
{
    use CheckPasswordLengthTrait;

    private $ignorePasswordCase;

    /**
     * @param bool $ignorePasswordCase Compare password case-insensitive
     */
    public function __construct(bool $ignorePasswordCase = false)
    {
        $this->ignorePasswordCase = $ignorePasswordCase;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(string $plainPassword, ?string $salt = null): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new InvalidPasswordException();
        }

        return $this->mergePasswordAndSalt($plainPassword, $salt);
    }

    public function verify(string $hashedPassword, string $plainPassword, ?string $salt = null): bool
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            return false;
        }

        $pass2 = $this->mergePasswordAndSalt($plainPassword, $salt);

        if (!$this->ignorePasswordCase) {
            return hash_equals($hashedPassword, $pass2);
        }

        return hash_equals(strtolower($hashedPassword), strtolower($pass2));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }

    private function mergePasswordAndSalt(string $password, ?string $salt): string
    {
        if (empty($salt)) {
            return $password;
        }

        if (false !== strrpos($salt, '{') || false !== strrpos($salt, '}')) {
            throw new \InvalidArgumentException('Cannot use { or } in salt.');
        }

        return $password.'{'.$salt.'}';
    }
}
