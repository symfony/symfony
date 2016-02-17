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

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;

/**
 * Ldap interface.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface LdapInterface extends BaseLdapInterface
{
    const ESCAPE_FILTER = 0x01;
    const ESCAPE_DN = 0x02;

    /**
     * Queries a ldap server for entries matching the given criteria.
     *
     * @param string $dn
     * @param string $query
     * @param array  $options
     *
     * @return QueryInterface
     */
    public function query($dn, $query, array $options = array());

    /**
     * @return EntryManagerInterface
     */
    public function getEntryManager();
}
