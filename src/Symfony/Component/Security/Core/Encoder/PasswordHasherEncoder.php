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
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * Forward compatibility for new new PasswordHasher component.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal To be removed in Symfony 6
 */
final class PasswordHasherEncoder implements PasswordEncoderInterface, SelfSaltingEncoderInterface
{
    private $passwordHasher;

    public function __construct(PasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function encodePassword(string $raw, ?string $salt): string
    {
        if (null !== $salt) {
            throw new \InvalidArgumentException('This password hasher does not support passing a salt.');
        }

        try {
            return $this->passwordHasher->hash($raw);
        } catch (InvalidPasswordException $e) {
            throw new BadCredentialsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isPasswordValid(string $encoded, string $raw, ?string $salt): bool
    {
        if (null !== $salt) {
            throw new \InvalidArgumentException('This password hasher does not support passing a salt.');
        }

        return $this->passwordHasher->verify($encoded, $raw);
    }

    public function needsRehash(string $encoded): bool
    {
        return $this->passwordHasher->needsRehash($encoded);
    }
}
