<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', Pbkdf2PasswordEncoder::class, Pbkdf2PasswordHasher::class);

/**
 * Pbkdf2PasswordEncoder uses the PBKDF2 (Password-Based Key Derivation Function 2).
 *
 * Providing a high level of Cryptographic security,
 *  PBKDF2 is recommended by the National Institute of Standards and Technology (NIST).
 *
 * But also warrants a warning, using PBKDF2 (with a high number of iterations) slows down the process.
 * PBKDF2 should be used with caution and care.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 * @author Andrew Johnson
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.3, use {@link Pbkdf2PasswordHasher} instead
 */
class Pbkdf2PasswordEncoder extends BasePasswordEncoder
{
    use LegacyEncoderTrait;

    /**
     * @param string $algorithm          The digest algorithm to use
     * @param bool   $encodeHashAsBase64 Whether to base64 encode the password hash
     * @param int    $iterations         The number of iterations to use to stretch the password hash
     * @param int    $length             Length of derived key to create
     */
    public function __construct(string $algorithm = 'sha512', bool $encodeHashAsBase64 = true, int $iterations = 1000, int $length = 40)
    {
        $this->hasher = new Pbkdf2PasswordHasher($algorithm, $encodeHashAsBase64, $iterations, $length);
    }
}
