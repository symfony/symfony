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

/**
 * Hashes passwords using the best available hasher.
 * Verifies them using a chain of hashers.
 *
 * /!\ Don't put a PlaintextPasswordHasher in the list as that'd mean a leaked hash
 * could be used to authenticate successfully without knowing the cleartext password.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class MigratingPasswordHasher implements PasswordHasherInterface
{
    private PasswordHasherInterface $bestHasher;
    private array $extraHashers;

    public function __construct(PasswordHasherInterface $bestHasher, PasswordHasherInterface ...$extraHashers)
    {
        $this->bestHasher = $bestHasher;
        $this->extraHashers = $extraHashers;
    }

    public function hash(#[\SensitiveParameter] string $plainPassword, string $salt = null): string
    {
        return $this->bestHasher->hash($plainPassword, $salt);
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword, string $salt = null): bool
    {
        if ($this->bestHasher->verify($hashedPassword, $plainPassword, $salt)) {
            return true;
        }

        if (!$this->bestHasher->needsRehash($hashedPassword)) {
            return false;
        }

        foreach ($this->extraHashers as $hasher) {
            if ($hasher->verify($hashedPassword, $plainPassword, $salt)) {
                return true;
            }
        }

        return false;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return $this->bestHasher->needsRehash($hashedPassword);
    }
}
