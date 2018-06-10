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

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * LdapBindAuthenticationProvider authenticates a user against an LDAP server.
 *
 * The only way to check user credentials is to try to connect the user with its
 * credentials to the ldap.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class LdapBindAuthenticationProvider extends UserAuthenticationProvider
{
    private $userProvider;
    private $ldap;
    private $dnString;
    private $queryString;

    public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, string $providerKey, LdapInterface $ldap, string $dnString = '{username}', bool $hideUserNotFoundExceptions = true)
    {
        parent::__construct($userChecker, $providerKey, $hideUserNotFoundExceptions);

        $this->userProvider = $userProvider;
        $this->ldap = $ldap;
        $this->dnString = $dnString;
    }

    /**
     * Set a query string to use in order to find a DN for the username.
     *
     * @param string $queryString
     */
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * {@inheritdoc}
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        if (AuthenticationProviderInterface::USERNAME_NONE_PROVIDED === $username) {
            throw new UsernameNotFoundException('Username can not be null');
        }

        return $this->userProvider->loadUserByUsername($username);
    }

    /**
     * {@inheritdoc}
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        $username = $token->getUsername();
        $password = $token->getCredentials();

        if ('' === (string) $password) {
            throw new BadCredentialsException('The presented password must not be empty.');
        }

        try {
            $username = $this->ldap->escape($username, '', LdapInterface::ESCAPE_DN);

            if ($this->queryString) {
                $query = str_replace('{username}', $username, $this->queryString);
                $result = $this->ldap->query($this->dnString, $query)->execute();
                if (1 !== $result->count()) {
                    throw new BadCredentialsException('The presented username is invalid.');
                }

                $dn = $result[0]->getDn();
            } else {
                $dn = str_replace('{username}', $username, $this->dnString);
            }

            $this->ldap->bind($dn, $password);
        } catch (ConnectionException $e) {
            throw new BadCredentialsException('The presented password is invalid.');
        }
    }
}
