<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter\ExtLdap;

use Symfony\Component\Ldap\Exception\LdapException;

/**
 * A class representing the Ldap extension's options, which can be used with
 * ldap_set_option or ldap_get_option.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @internal
 */
final class ConnectionOptions
{
    const API_INFO = 0x00;
    const DEREF = 0x02;
    const SIZELIMIT = 0x03;
    const TIMELIMIT = 0x04;
    const REFERRALS = 0x08;
    const RESTART = 0x09;
    const PROTOCOL_VERSION = 0x11;
    const SERVER_CONTROLS = 0x12;
    const CLIENT_CONTROLS = 0x13;
    const API_FEATURE_INFO = 0x15;
    const HOST_NAME = 0x30;
    const ERROR_NUMBER = 0x31;
    const ERROR_STRING = 0x32;
    const MATCHED_DN = 0x33;
    const DEBUG_LEVEL = 0x5001;
    const NETWORK_TIMEOUT = 0x5005;
    const X_SASL_MECH = 0x6100;
    const X_SASL_REALM = 0x6101;
    const X_SASL_AUTHCID = 0x6102;
    const X_SASL_AUTHZID = 0x6103;

    public static function getOptionName($name)
    {
        return sprintf('%s::%s', self::class, strtoupper($name));
    }

    /**
     * Fetches an option's corresponding constant value from an option name.
     * The option name can either be in snake or camel case.
     *
     * @param string $name
     *
     * @return int
     *
     * @throws LdapException
     */
    public static function getOption($name)
    {
        // Convert
        $constantName = self::getOptionName($name);

        if (!defined($constantName)) {
            throw new LdapException(sprintf('Unknown option "%s"', $name));
        }

        return constant($constantName);
    }

    public static function isOption($name)
    {
        return defined(self::getOptionName($name));
    }
}
