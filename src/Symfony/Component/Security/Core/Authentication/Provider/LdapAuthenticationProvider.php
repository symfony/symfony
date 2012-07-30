<?php

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\LdapUserProviderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * LdapAuthenticationProvider uses a LdapUserProviderInterface to retrieve the user
 * for a UsernamePasswordToken.
 *
 * The only way to check user credentials is to try to connect the user with its
 * credentials to the ldap.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class LdapAuthenticationProvider extends UserAuthenticationProvider
{
    private $userProvider;
    private $ldap;

    /**
     * Constructor.
     *
     * @param LdapUserProviderInterface $userProvider               An LdapUserProvider instance
     * @param UserCheckerInterface      $userChecker                An UserCheckerInterface instance
     * @param string                    $providerKey                The provider key
     * @param Boolean                   $hideUserNotFoundExceptions Whether to hide user not found exception or not
     */
    public function __construct(LdapUserProviderInterface $userProvider, UserCheckerInterface $userChecker, $providerKey, $hideUserNotFoundExceptions = true)
    {
        parent::__construct($userChecker, $providerKey, $hideUserNotFoundExceptions);

        $this->userProvider = $userProvider;
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        if ("" === ($presentedPassword = $token->getCredentials())) {
            throw new BadCredentialsException('The presented password cannot be empty.');
        }

        // At this point, the $user is already authenticated
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        $user = $token->getUser();
        if ($user instanceof UserInterface) {
            return $user;
        }

        try {
            $user = $this->userProvider->loadUserByUsernameAndPassword($username, $token->getCredentials());

            if (!$user instanceof UserInterface) {
                throw new AuthenticationServiceException('The user provider must return a UserInterface object.');
            }

            return $user;
        } catch (UsernameNotFoundException $notFound) {
            throw $notFound;
        }
    }
}
