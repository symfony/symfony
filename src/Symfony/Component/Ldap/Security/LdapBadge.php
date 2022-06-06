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

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * A badge indicating that the credentials should be checked using LDAP.
 *
 * This badge must be used together with PasswordCredentials.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class LdapBadge implements BadgeInterface
{
    private $resolved = false;
    private $ldapServiceId;
    private $dnString;
    private $searchDn;
    private $searchPassword;
    private $queryString;

    public function __construct(string $ldapServiceId, string $dnString = '{username}', string $searchDn = '', string $searchPassword = '', string $queryString = null)
    {
        $this->ldapServiceId = $ldapServiceId;
        $this->dnString = $dnString;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->queryString = $queryString;
    }

    public function getLdapServiceId(): string
    {
        return $this->ldapServiceId;
    }

    public function getDnString(): string
    {
        return $this->dnString;
    }

    public function getSearchDn(): string
    {
        return $this->searchDn;
    }

    public function getSearchPassword(): string
    {
        return $this->searchPassword;
    }

    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
