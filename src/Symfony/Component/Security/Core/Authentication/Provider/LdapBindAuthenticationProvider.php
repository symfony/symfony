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

use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', LdapBindAuthenticationProvider::class);

/**
 * LdapBindAuthenticationProvider authenticates a user against an LDAP server.
 *
 * The only way to check user credentials is to try to connect the user with its
 * credentials to the ldap.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class LdapBindAuthenticationProvider extends UserAuthenticationProvider
{
    private $userProvider;
    private $ldap;
    private $dnString;
    private $queryString;
    private $searchDn;
    private $searchPassword;

    public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, string $providerKey, LdapInterface $ldap, string $dnString = '{user_identifier}', bool $hideUserNotFoundExceptions = true, string $searchDn = '', string $searchPassword = '')
    {
        parent::__construct($userChecker, $providerKey, $hideUserNotFoundExceptions);

        $this->userProvider = $userProvider;
        $this->ldap = $ldap;
        $this->dnString = $dnString;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
    }

    /**
     * Set a query string to use in order to find a DN for the user identifier.
     */
    public function setQueryString(string $queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser(string $userIdentifier, UsernamePasswordToken $token)
    {
        if (AuthenticationProviderInterface::USERNAME_NONE_PROVIDED === $userIdentifier) {
            throw new UserNotFoundException('User identifier can not be null.');
        }

        // @deprecated since 5.3, change to $this->userProvider->loadUserByIdentifier() in 6.0
        if (method_exists($this->userProvider, 'loadUserByIdentifier')) {
            return $this->userProvider->loadUserByIdentifier($userIdentifier);
        } else {
            trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($this->userProvider));

            return $this->userProvider->loadUserByUsername($userIdentifier);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        // @deprecated since 5.3, change to $token->getUserIdentifier() in 6.0
        $userIdentifier = method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername();
        $password = $token->getCredentials();

        if ('' === (string) $password) {
            throw new BadCredentialsException('The presented password must not be empty.');
        }

        try {
            if ($this->queryString) {
                if ('' !== $this->searchDn && '' !== $this->searchPassword) {
                    $this->ldap->bind($this->searchDn, $this->searchPassword);
                } else {
                    throw new LogicException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
                }
                $userIdentifier = $this->ldap->escape($userIdentifier, '', LdapInterface::ESCAPE_FILTER);
                $query = str_replace(['{username}', '{user_identifier}'], $userIdentifier, $this->queryString);
                $result = $this->ldap->query($this->dnString, $query)->execute();
                if (1 !== $result->count()) {
                    throw new BadCredentialsException('The presented username is invalid.');
                }

                $dn = $result[0]->getDn();
            } else {
                $userIdentifier = $this->ldap->escape($userIdentifier, '', LdapInterface::ESCAPE_DN);
                $dn = str_replace(['{username}', '{user_identifier}'], $userIdentifier, $this->dnString);
            }

            $this->ldap->bind($dn, $password);
        } catch (ConnectionException $e) {
            throw new BadCredentialsException('The presented password is invalid.');
        }
    }
}
