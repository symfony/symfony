<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Exception\AlreadyExistsException;
use Symfony\Component\Ldap\Exception\ConnectionTimeoutException;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @method void   saslBind(?string $dn = null, #[\SensitiveParameter] ?string $password = null, ?string $mech = null, ?string $realm = null, ?string $authcId = null, ?string $authzId = null, ?string $props =  null)
 * @method string whoami()
 */
interface ConnectionInterface
{
    /**
     * Checks whether the connection was already bound or not.
     */
    public function isBound(): bool;

    /**
     * Binds the connection against a user's DN and password.
     *
     * @throws AlreadyExistsException      When the connection can't be created because of an LDAP_ALREADY_EXISTS error
     * @throws ConnectionTimeoutException  When the connection can't be created because of an LDAP_TIMEOUT error
     * @throws InvalidCredentialsException When the connection can't be created because of an LDAP_INVALID_CREDENTIALS error
     */
    public function bind(?string $dn = null, #[\SensitiveParameter] ?string $password = null): void;

    /*
     * Binds the connection against a user's DN and password using SASL.
     *
     * @throws LdapException               When SASL support is not available
     * @throws AlreadyExistsException      When the connection can't be created because of an LDAP_ALREADY_EXISTS error
     * @throws ConnectionTimeoutException  When the connection can't be created because of an LDAP_TIMEOUT error
     * @throws InvalidCredentialsException When the connection can't be created because of an LDAP_INVALID_CREDENTIALS error
     */
    // public function saslBind(?string $dn = null, #[\SensitiveParameter] ?string $password = null, ?string $mech = null, ?string $realm = null, ?string $authcId = null, ?string $authzId = null, ?string $props = null): void;

    /*
     * Return authenticated and authorized (for SASL) DN.
     */
    // public function whoami(): string;
}
