<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Encoder;

use Symphony\Component\Security\Core\User\UserInterface;

/**
 * EncoderFactoryInterface to support different encoders for different accounts.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface EncoderFactoryInterface
{
    /**
     * Returns the password encoder to use for the given account.
     *
     * @param UserInterface|string $user A UserInterface instance or a class name
     *
     * @return PasswordEncoderInterface
     *
     * @throws \RuntimeException when no password encoder could be found for the user
     */
    public function getEncoder($user);
}
