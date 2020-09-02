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

@trigger_error('The '.__NAMESPACE__.'\LdapClient class is deprecated since Symfony 3.1 and will be removed in 4.0. Use the Ldap class directly instead.', \E_USER_DEPRECATED);

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @deprecated since version 3.1, to be removed in 4.0. Use the Ldap class instead.
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
    public function query($dn, $query, array $options = [])
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
        @trigger_error('The "find" method is deprecated since Symfony 3.1 and will be removed in 4.0. Use the "query" method instead.', \E_USER_DEPRECATED);

        $query = $this->ldap->query($dn, $query, ['filter' => $filter]);
        $entries = $query->execute();
        $result = [
            'count' => 0,
        ];

        foreach ($entries as $entry) {
            $resultEntry = [];

            foreach ($entry->getAttributes() as $attribute => $values) {
                $resultAttribute = [
                    'count' => \count($values),
                ];

                foreach ($values as $val) {
                    $resultAttribute[] = $val;
                }
                $attributeName = strtolower($attribute);

                $resultAttribute['count'] = \count($values);
                $resultEntry[$attributeName] = $resultAttribute;
                $resultEntry[] = $attributeName;
            }

            $resultEntry['count'] = \count($resultEntry) / 2;
            $resultEntry['dn'] = $entry->getDn();
            $result[] = $resultEntry;
        }

        $result['count'] = \count($result) - 1;

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

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'options' => [
                'protocol_version' => $version,
                'referrals' => (bool) $optReferrals,
            ],
        ];
    }
}
