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

@trigger_error('The '.__NAMESPACE__.'\LdapClient class is deprecated since version 3.1 and will be removed in 4.0. Use the Ldap class directly instead.', E_USER_DEPRECATED);

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @deprecated The LdapClient class will be removed in Symfony 4.0. You should use the Ldap class instead.
 */
final class LdapClient implements LdapClientInterface
{
    private $ldap;

    public function __construct($host = null, $port = 389, $version = 3, $useSsl = false, $useStartTls = false, $optReferrals = false, LdapInterface $ldap = null)
    {
        $config = $this->normalizeConfig($host, $port, $version, $useSsl, $useStartTls, $optReferrals);

        $this->ldap = null !== $ldap ? $ldap : Ldap::create('ext_ldap', $config);
    }

    /**
     * {@inheritdoc}
     */
    public function bind($dn = null, $password = null)
    {
        $this->ldap->bind($dn, $password);
    }

    /**
     * {@inheritdoc}
     */
    public function query($dn, $query, array $options = array())
    {
        return $this->ldap->query($dn, $query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getEntryManager()
    {
        return $this->ldap->getEntryManager();
    }

    /**
     * {@inheritdoc}
     */
    public function find($dn, $query, $filter = '*')
    {
        @trigger_error('The "find" method is deprecated since version 3.1 and will be removed in 4.0. Use the "query" method instead.', E_USER_DEPRECATED);

        $query = $this->ldap->query($dn, $query, array('filter' => $filter));
        $entries = $query->execute();
        $result = array();

        foreach ($entries as $entry) {
            $resultEntry = array();

            foreach ($entry->getAttributes() as $attribute => $values) {
                $resultAttribute = $values;

                $resultAttribute['count'] = count($values);
                $resultEntry[] = $resultAttribute;
                $resultEntry[$attribute] = $resultAttribute;
            }

            $resultEntry['count'] = count($resultEntry) / 2;
            $result[] = $resultEntry;
        }

        $result['count'] = count($result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($subject, $ignore = '', $flags = 0)
    {
        return $this->ldap->escape($subject, $ignore, $flags);
    }

    private function normalizeConfig($host, $port, $version, $useSsl, $useStartTls, $optReferrals)
    {
        if ((bool) $useSsl) {
            $encryption = 'ssl';
        } elseif ((bool) $useStartTls) {
            $encryption = 'tls';
        } else {
            $encryption = 'none';
        }

        return array(
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'options' => array(
                'protocol_version' => $version,
                'referrals' => (bool) $optReferrals,
            ),
        );
    }
}
