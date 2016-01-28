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
    private $adapterMap = array(
        'ext_ldap' => 'Symfony\Component\Ldap\Driver\ExtLdap\Adapter',
    );

    public function create($adapter, $options)
    {
        if (!isset($this->adapterMap[$adapter])) {
            throw new DriverNotFoundException(sprintf('Adapter "%s" not found', $adapter));
        }

        $class = $this->adapterMap[$adapter];

        return new Ldap(new $class($options));
    }
}
