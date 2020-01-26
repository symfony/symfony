<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication\Authenticator;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @property EncoderFactoryInterface $encoderFactory
 *
 * @experimental in 5.1
 */
trait UsernamePasswordTrait
{
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        if (!$this->encoderFactory instanceof EncoderFactoryInterface) {
            throw new \LogicException(\get_class($this).' uses the '.__CLASS__.' trait, which requires an $encoderFactory property to be initialized with an '.EncoderFactoryInterface::class.' implementation.');
        }

        if ('' === $credentials['password']) {
            throw new BadCredentialsException('The presented password cannot be empty.');
        }

        if (!$this->encoderFactory->getEncoder($user)->isPasswordValid($user->getPassword(), $credentials['password'], null)) {
            throw new BadCredentialsException('The presented password is invalid.');
        }

        return true;
    }

    public function createAuthenticatedToken(UserInterface $user, $providerKey): TokenInterface
    {
        return new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
    }
}
