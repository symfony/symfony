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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserPasswordEncoderInterface is the interface for the password encoder service.
 *
 * @author Ariel Ferrandini <arielferrandini@gmail.com>
 */
interface UserPasswordEncoderInterface
{
    /**
     * Encodes the plain password.
     *
     * @param UserInterface $user          The user
     * @param string        $plainPassword The password to encode
     *
     * @return string The encoded password
     */
    public function encodePassword(UserInterface $user, $plainPassword);

    /**
     * @param UserInterface $user The user
     * @param string        $raw  A raw password
     *
     * @return bool true if the password is valid, false otherwise
     */
    public function isPasswordValid(UserInterface $user, $raw);
}
