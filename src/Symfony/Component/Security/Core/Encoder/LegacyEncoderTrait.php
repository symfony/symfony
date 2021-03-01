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

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * @internal
 */
trait LegacyEncoderTrait
{
    /**
     * @var PasswordHasherInterface|LegacyPasswordHasherInterface
     */
    private $hasher;

    /**
     * {@inheritdoc}
     */
    public function encodePassword(string $raw, ?string $salt): string
    {
        try {
            return $this->hasher->hash($raw, $salt);
        } catch (InvalidPasswordException $e) {
            throw new BadCredentialsException('Bad credentials.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid(string $encoded, string $raw, ?string $salt): bool
    {
        return $this->hasher->verify($encoded, $raw, $salt);
    }

    /**
     * {@inheritdoc}
     */
    public function needsRehash(string $encoded): bool
    {
        return $this->hasher->needsRehash($encoded);
    }
}
