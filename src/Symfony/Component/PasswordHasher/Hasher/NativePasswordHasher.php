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
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Hashes passwords using password_hash().
 *
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 * @author Terje Bråten <terje@braten.be>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class NativePasswordHasher implements PasswordHasherInterface
{
    use CheckPasswordLengthTrait;

    private $algo = \PASSWORD_BCRYPT;
    private $options;

    /**
     * @param string|null $algo An algorithm supported by password_hash() or null to use the best available algorithm
     */
    public function __construct(int $opsLimit = null, int $memLimit = null, int $cost = null, ?string $algo = null)
    {
        $cost = $cost ?? 13;
        $opsLimit = $opsLimit ?? max(4, \defined('SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE : 4);
        $memLimit = $memLimit ?? max(64 * 1024 * 1024, \defined('SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE') ? \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE : 64 * 1024 * 1024);

        if (3 > $opsLimit) {
            throw new \InvalidArgumentException('$opsLimit must be 3 or greater.');
        }

        if (10 * 1024 > $memLimit) {
            throw new \InvalidArgumentException('$memLimit must be 10k or greater.');
        }

        if ($cost < 4 || 31 < $cost) {
            throw new \InvalidArgumentException('$cost must be in the range of 4-31.');
        }

        $algos = [1 => \PASSWORD_BCRYPT, '2y' => \PASSWORD_BCRYPT];

        if (\defined('PASSWORD_ARGON2I')) {
            $algos[2] = $algos['argon2i'] = (string) \PASSWORD_ARGON2I;
        }

        if (\defined('PASSWORD_ARGON2ID')) {
            $algos[3] = $algos['argon2id'] = (string) \PASSWORD_ARGON2ID;
        }

        if (null !== $algo) {
            $this->algo = $algos[$algo] ?? $algo;
        }

        $this->options = [
            'cost' => $cost,
            'time_cost' => $opsLimit,
            'memory_cost' => $memLimit >> 10,
            'threads' => 1,
        ];
    }

    public function hash(string $plainPassword): string
    {
        if ($this->isPasswordTooLong($plainPassword) || ((string) \PASSWORD_BCRYPT === $this->algo && 72 < \strlen($plainPassword))) {
            throw new InvalidPasswordException();
        }

        return password_hash($plainPassword, $this->algo, $this->options);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if ('' === $plainPassword || $this->isPasswordTooLong($plainPassword)) {
            return false;
        }

        if (0 !== strpos($hashedPassword, '$argon')) {
            // BCrypt encodes only the first 72 chars
            return (72 >= \strlen($plainPassword) || 0 !== strpos($hashedPassword, '$2')) && password_verify($plainPassword, $hashedPassword);
        }

        if (\extension_loaded('sodium') && version_compare(\SODIUM_LIBRARY_VERSION, '1.0.14', '>=')) {
            return sodium_crypto_pwhash_str_verify($hashedPassword, $plainPassword);
        }

        if (\extension_loaded('libsodium') && version_compare(phpversion('libsodium'), '1.0.14', '>=')) {
            return \Sodium\crypto_pwhash_str_verify($hashedPassword, $plainPassword);
        }

        return password_verify($plainPassword, $hashedPassword);
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, $this->algo, $this->options);
    }
}
