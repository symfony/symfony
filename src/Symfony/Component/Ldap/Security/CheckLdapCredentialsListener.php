<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\InvalidSearchCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Verifies password credentials using an LDAP service whenever the
 * LdapBadge is attached to the Security passport.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class CheckLdapCredentialsListener implements EventSubscriberInterface
{
    private ContainerInterface $ldapLocator;

    public function __construct(ContainerInterface $ldapLocator)
    {
        $this->ldapLocator = $ldapLocator;
    }

    /**
     * @return void
     */
    public function onCheckPassport(CheckPassportEvent $event)
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(LdapBadge::class)) {
            return;
        }

        /** @var LdapBadge $ldapBadge */
        $ldapBadge = $passport->getBadge(LdapBadge::class);
        if ($ldapBadge->isResolved()) {
            return;
        }

        if (!$passport->hasBadge(PasswordCredentials::class)) {
            throw new \LogicException(sprintf('LDAP authentication requires a passport containing password credentials, authenticator "%s" does not fulfill these requirements.', $event->getAuthenticator()::class));
        }

        /** @var PasswordCredentials $passwordCredentials */
        $passwordCredentials = $passport->getBadge(PasswordCredentials::class);
        if ($passwordCredentials->isResolved()) {
            throw new \LogicException('LDAP authentication password verification cannot be completed because something else has already resolved the PasswordCredentials.');
        }

        if (!$this->ldapLocator->has($ldapBadge->getLdapServiceId())) {
            throw new \LogicException(sprintf('Cannot check credentials using the "%s" ldap service, as such service is not found. Did you maybe forget to add the "ldap" service tag to this service?', $ldapBadge->getLdapServiceId()));
        }

        $presentedPassword = $passwordCredentials->getPassword();
        if ('' === $presentedPassword) {
            throw new BadCredentialsException('The presented password cannot be empty.');
        }

        $user = $passport->getUser();

        /** @var LdapInterface $ldap */
        $ldap = $this->ldapLocator->get($ldapBadge->getLdapServiceId());
        try {
            if ($ldapBadge->getQueryString()) {
                if ('' !== $ldapBadge->getSearchDn() && '' !== $ldapBadge->getSearchPassword()) {
                    try {
                        $ldap->bind($ldapBadge->getSearchDn(), $ldapBadge->getSearchPassword());
                    } catch (InvalidCredentialsException) {
                        throw new InvalidSearchCredentialsException();
                    }
                } else {
                    throw new LogicException('Using the "query_string" config without using a "search_dn" and a "search_password" is not supported.');
                }
                $identifier = $ldap->escape($user->getUserIdentifier(), '', LdapInterface::ESCAPE_FILTER);
                $query = str_replace('{user_identifier}', $identifier, $ldapBadge->getQueryString());
                $result = $ldap->query($ldapBadge->getDnString(), $query)->execute();
                if (1 !== $result->count()) {
                    throw new BadCredentialsException('The presented user identifier is invalid.');
                }

                $dn = $result[0]->getDn();
            } else {
                $identifier = $ldap->escape($user->getUserIdentifier(), '', LdapInterface::ESCAPE_DN);
                $dn = str_replace('{user_identifier}', $identifier, $ldapBadge->getDnString());
            }

            $ldap->bind($dn, $presentedPassword);
        } catch (InvalidCredentialsException) {
            throw new BadCredentialsException('The presented password is invalid.');
        }

        $passwordCredentials->markResolved();
        $ldapBadge->markResolved();
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['onCheckPassport', 144]];
    }
}
