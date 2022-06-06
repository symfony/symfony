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

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" interface is deprecated, use "%s" instead.', UserPasswordEncoderInterface::class, UserPasswordHasherInterface::class);

/**
 * UserPasswordEncoderInterface is the interface for the password encoder service.
 *
 * @author Ariel Ferrandini <arielferrandini@gmail.com>
 *
 * @deprecated since Symfony 5.3, use {@link UserPasswordHasherInterface} instead
 */
interface UserPasswordEncoderInterface
{
    /**
     * Encodes the plain password.
     *
     * @return string
     */
    public function encodePassword(UserInterface $user, string $plainPassword);

    /**
     * @return bool
     */
    public function isPasswordValid(UserInterface $user, string $raw);

    /**
     * Checks if an encoded password would benefit from rehashing.
     */
    public function needsRehash(UserInterface $user): bool;
}
