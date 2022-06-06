<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', DaoAuthenticationProvider::class);

/**
 * DaoAuthenticationProvider uses a UserProviderInterface to retrieve the user
 * for a UsernamePasswordToken.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class DaoAuthenticationProvider extends UserAuthenticationProvider
{
    private $hasherFactory;
    private $userProvider;

    /**
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, string $providerKey, $hasherFactory, bool $hideUserNotFoundExceptions = true)
    {
        parent::__construct($userChecker, $providerKey, $hideUserNotFoundExceptions);

        if ($hasherFactory instanceof EncoderFactoryInterface) {
            trigger_deprecation('symfony/security-core', '5.3', 'Passing a "%s" instance to the "%s" constructor is deprecated, use "%s" instead.', EncoderFactoryInterface::class, __CLASS__, PasswordHasherFactoryInterface::class);
        }

        $this->hasherFactory = $hasherFactory;
        $this->userProvider = $userProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        $currentUser = $token->getUser();
        if ($currentUser instanceof UserInterface) {
            if ($currentUser->getPassword() !== $user->getPassword()) {
                throw new BadCredentialsException('The credentials were changed from another session.');
            }
        } else {
            if ('' === ($presentedPassword = $token->getCredentials())) {
                throw new BadCredentialsException('The presented password cannot be empty.');
            }

            if (null === $user->getPassword()) {
                throw new BadCredentialsException('The presented password is invalid.');
            }

            if (!$user instanceof PasswordAuthenticatedUserInterface) {
                trigger_deprecation('symfony/security-core', '5.3', 'Using password-based authentication listeners while not implementing "%s" interface from class "%s" is deprecated.', PasswordAuthenticatedUserInterface::class, get_debug_type($user));
            }

            $salt = $user->getSalt();
            if ($salt && !$user instanceof LegacyPasswordAuthenticatedUserInterface) {
                trigger_deprecation('symfony/security-core', '5.3', 'Returning a string from "getSalt()" without implementing the "%s" interface is deprecated, the "%s" class should implement it.', LegacyPasswordAuthenticatedUserInterface::class, get_debug_type($user));
            }

            // deprecated since Symfony 5.3
            if ($this->hasherFactory instanceof EncoderFactoryInterface) {
                $encoder = $this->hasherFactory->getEncoder($user);

                if (!$encoder->isPasswordValid($user->getPassword(), $presentedPassword, $salt)) {
                    throw new BadCredentialsException('The presented password is invalid.');
                }

                if ($this->userProvider instanceof PasswordUpgraderInterface && method_exists($encoder, 'needsRehash') && $encoder->needsRehash($user->getPassword())) {
                    $this->userProvider->upgradePassword($user, $encoder->encodePassword($presentedPassword, $user->getSalt()));
                }

                return;
            }

            $hasher = $this->hasherFactory->getPasswordHasher($user);

            if (!$hasher->verify($user->getPassword(), $presentedPassword, $salt)) {
                throw new BadCredentialsException('The presented password is invalid.');
            }

            if ($this->userProvider instanceof PasswordUpgraderInterface && $hasher->needsRehash($user->getPassword())) {
                $this->userProvider->upgradePassword($user, $hasher->hash($presentedPassword, $salt));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser(string $userIdentifier, UsernamePasswordToken $token)
    {
        $user = $token->getUser();
        if ($user instanceof UserInterface) {
            return $user;
        }

        try {
            // @deprecated since Symfony 5.3, change to $this->userProvider->loadUserByIdentifier() in 6.0
            if (method_exists($this->userProvider, 'loadUserByIdentifier')) {
                $user = $this->userProvider->loadUserByIdentifier($userIdentifier);
            } else {
                trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($this->userProvider));

                $user = $this->userProvider->loadUserByUsername($userIdentifier);
            }

            if (!$user instanceof UserInterface) {
                throw new AuthenticationServiceException('The user provider must return a UserInterface object.');
            }

            return $user;
        } catch (UserNotFoundException $e) {
            $e->setUserIdentifier($userIdentifier);
            throw $e;
        } catch (\Exception $e) {
            $e = new AuthenticationServiceException($e->getMessage(), 0, $e);
            $e->setToken($token);
            throw $e;
        }
    }
}
