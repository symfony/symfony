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

use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

/**
 * Forward compatibility for new new PasswordHasher component.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal To be removed in Symfony 6
 */
final class PasswordHasherAdapter implements LegacyPasswordHasherInterface
{
    private $passwordEncoder;

    public function __construct(PasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function hash(string $plainPassword, ?string $salt = null): string
    {
        return $this->passwordEncoder->encodePassword($plainPassword, $salt);
    }

    public function verify(string $hashedPassword, string $plainPassword, ?string $salt = null): bool
    {
        return $this->passwordEncoder->isPasswordValid($hashedPassword, $plainPassword, $salt);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return $this->passwordEncoder->needsRehash($hashedPassword);
    }
}
