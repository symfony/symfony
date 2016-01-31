<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap;

use Symfony\Component\Ldap\Exception\DriverNotFoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
final class LdapFactory
{
    private static $adapterMap = array(
        'ext_ldap' => 'Symfony\Component\Ldap\Adapter\ExtLdap\Adapter',
    );

    public static function create($adapter, $options)
    {
        if (!isset(self::$adapterMap[$adapter])) {
            throw new DriverNotFoundException(sprintf('Adapter "%s" not found', $adapter));
        }

        $class = self::$adapterMap[$adapter];

        return new Ldap(new $class($options));
    }
}
