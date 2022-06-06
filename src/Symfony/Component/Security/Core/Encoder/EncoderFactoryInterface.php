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

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use "%s" instead.', EncoderFactoryInterface::class, PasswordHasherFactoryInterface::class);

/**
 * EncoderFactoryInterface to support different encoders for different accounts.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.3, use {@link PasswordHasherFactoryInterface} instead
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
