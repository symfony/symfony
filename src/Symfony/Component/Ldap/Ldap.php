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

use Symfony\Component\Ldap\Adapter\AdapterInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
final class Ldap implements LdapInterface
{
    private $adapter;

    /**
     * Constructor.
     *
     * @param AdapterInterface $driver A configured Ldap adapter
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function bind($dn = null, $password = null)
    {
        $this->adapter->getConnection()->bind($dn, $password);
    }

    /**
     * {@inheritdoc}
     */
    public function query($dn, $query, array $options = array())
    {
        return $this->adapter->createQuery($dn, $query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function escape($subject, $ignore = '', $flags = 0)
    {
        return $this->adapter->escape($subject, $ignore, $flags);
    }
}
