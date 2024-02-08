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
use Symfony\Component\PasswordHasher\Exception\LogicException;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

/**
 * MessageDigestPasswordHasher uses a message digest algorithm.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MessageDigestPasswordHasher implements LegacyPasswordHasherInterface
{
    use CheckPasswordLengthTrait;

    private string $algorithm;
    private bool $encodeHashAsBase64;
    private int $iterations = 1;
    private int $hashLength = -1;

    /**
     * @param string $algorithm          The digest algorithm to use
     * @param bool   $encodeHashAsBase64 Whether to base64 encode the password hash
     * @param int    $iterations         The number of iterations to use to stretch the password hash
     */
    public function __construct(string $algorithm = 'sha512', bool $encodeHashAsBase64 = true, int $iterations = 5000)
    {
        $this->algorithm = $algorithm;
        $this->encodeHashAsBase64 = $encodeHashAsBase64;

        try {
            $this->hashLength = \strlen($this->hash('', 'salt'));
        } catch (\LogicException) {
            // ignore algorithm not supported
        }

        $this->iterations = $iterations;
    }

    public function hash(#[\SensitiveParameter] string $plainPassword, ?string $salt = null): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new InvalidPasswordException();
        }

        if (!\in_array($this->algorithm, hash_algos(), true)) {
            throw new LogicException(sprintf('The algorithm "%s" is not supported.', $this->algorithm));
        }

        $salted = $this->mergePasswordAndSalt($plainPassword, $salt);
        $digest = hash($this->algorithm, $salted, true);

        // "stretch" hash
        for ($i = 1; $i < $this->iterations; ++$i) {
            $digest = hash($this->algorithm, $digest.$salted, true);
        }

        return $this->encodeHashAsBase64 ? base64_encode($digest) : bin2hex($digest);
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, ?string $salt = null): bool
    {
        if (\strlen($hashedPassword) !== $this->hashLength || str_contains($hashedPassword, '$')) {
            return false;
        }

        return !$this->isPasswordTooLong($plainPassword) && hash_equals($hashedPassword, $this->hash($plainPassword, $salt));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }

    private function mergePasswordAndSalt(#[\SensitiveParameter] string $password, ?string $salt): string
    {
        if (!$salt) {
            return $password;
        }

        if (false !== strrpos($salt, '{') || false !== strrpos($salt, '}')) {
            throw new \InvalidArgumentException('Cannot use { or } in salt.');
        }

        return $password.'{'.$salt.'}';
    }
}
