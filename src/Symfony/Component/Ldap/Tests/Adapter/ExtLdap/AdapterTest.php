<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Adapter\ExtLdap;

use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Adapter\ExtLdap\Collection;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Tests\LdapTestCase;

/**
 * @requires extension ldap
 *
 * @group integration
 */
class AdapterTest extends LdapTestCase
{
    public function testLdapEscape()
    {
        $ldap = new Adapter();

        $this->assertEquals('\20foo\3dbar\0d(baz)*\20', $ldap->escape(" foo=bar\r(baz)* ", '', LdapInterface::ESCAPE_DN));
    }

    /**
     * @group functional
     */
    public function testSaslBind()
    {
        $h = @ldap_connect('ldap://'.getenv('LDAP_HOST').':'.getenv('LDAP_PORT'));
        @ldap_set_option($h, \LDAP_OPT_PROTOCOL_VERSION, 3);

        if (!$h || !@ldap_bind($h)) {
            $this->markTestSkipped('No server is listening on LDAP_HOST:LDAP_PORT');
        }

        if (!@ldap_start_tls($h)) {
            ldap_unbind($h);
            $this->markTestSkipped('Cannot establish an encrypted connection');
        }

        ldap_unbind($h);

        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->saslBind('cn=admin,dc=symfony,dc=com', 'symfony');
        $this->assertEquals('cn=admin,dc=symfony,dc=com', $ldap->getConnection()->whoami());
    }

    /**
     * @group functional
     */
    public function testWhoamiWithoutSaslBind()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $this->expectException(NotBoundException::class);
        $this->expectExceptionMessage('Cannot execute "Symfony\Component\Ldap\Adapter\ExtLdap\Connection::whoami()" before calling "Symfony\Component\Ldap\Adapter\ExtLdap\Connection::saslBind()".');

        $ldap->getConnection()->whoami();
    }

    /**
     * @group functional
     */
    public function testLdapQuery()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', []);
        $result = $query->execute();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);

        $entry = $result[0];
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertEquals(['Fabien Potencier'], $entry->getAttribute('cn'));
        $this->assertEquals(['fabpot@symfony.com', 'fabien@potencier.com'], $entry->getAttribute('mail'));
    }

    /**
     * @group functional
     */
    public function testLdapQueryIterator()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', []);
        $result = $query->execute();
        $iterator = $result->getIterator();
        $iterator->rewind();
        $entry = $iterator->current();
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertEquals(['Fabien Potencier'], $entry->getAttribute('cn'));
        $this->assertEquals(['fabpot@symfony.com', 'fabien@potencier.com'], $entry->getAttribute('mail'));
    }

    /**
     * @group functional
     */
    public function testLdapQueryWithoutBind()
    {
        $ldap = new Adapter($this->getLdapConfig());
        $this->expectException(NotBoundException::class);
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', []);
        $query->execute();
    }

    public function testLdapQueryScopeBase()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');

        $query = $ldap->createQuery('cn=Fabien Potencier,dc=symfony,dc=com', '(objectclass=*)', [
            'scope' => Query::SCOPE_BASE,
        ]);
        $result = $query->execute();

        $entry = $result[0];
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Fabien Potencier'], $entry->getAttribute('cn'));
    }

    public function testLdapQueryScopeOneLevel()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');

        $one_level_result = $ldap->createQuery('ou=Components,dc=symfony,dc=com', '(objectclass=*)', [
            'scope' => Query::SCOPE_ONE,
        ])->execute();

        $subtree_count = $ldap->createQuery('ou=Components,dc=symfony,dc=com', '(objectclass=*)')->execute()->count();

        $this->assertNotEquals($one_level_result->count(), $subtree_count);
        $this->assertEquals(1, $one_level_result->count());
        $this->assertEquals(['Ldap'], $one_level_result[0]->getAttribute('ou'));
    }

    public function testLdapPagination()
    {
        $ldap = new Adapter($this->getLdapConfig());
        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $entries = $this->setupTestUsers($ldap);

        $unpagedQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
        ]);
        $fullyPagedQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 25,
        ]);
        $pagedQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 5,
        ]);

        try {
            // All four of the above queries should result in the 25 'users' being returned
            $this->assertCount(25, $unpagedQuery->execute());
            $this->assertCount(25, $fullyPagedQuery->execute());
            $this->assertCount(25, $pagedQuery->execute());

            // They should also result in 1 or 25 / pageSize results
            $this->assertCount(1, $unpagedQuery->getResources());
            $this->assertCount(1, $fullyPagedQuery->getResources());
            $this->assertCount(5, $pagedQuery->getResources());

            // This last query is to ensure that we haven't botched the state of our connection
            // by not resetting pagination properly.
            $finalQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
                'scope' => Query::SCOPE_ONE,
            ]);

            $this->assertCount(25, $finalQuery->execute());
            $this->assertCount(1, $finalQuery->getResources());
        } catch (LdapException $exc) {
            $this->markTestSkipped('Test LDAP server does not support pagination');
        }

        $this->destroyEntries($ldap, $entries);
    }

    private function setupTestUsers($ldap)
    {
        $entries = [];

        // Create 25 'users' that we'll query for in different page sizes
        $em = $ldap->getEntryManager();
        for ($i = 0; $i < 25; ++$i) {
            $cn = \sprintf('user%d', $i);
            $entry = new Entry(\sprintf('cn=%s,dc=symfony,dc=com', $cn));
            $entry->setAttribute('objectClass', ['applicationProcess']);
            $entry->setAttribute('cn', [$cn]);
            try {
                $em->add($entry);
            } catch (LdapException $exc) {
                // ignored
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    private function destroyEntries($ldap, $entries)
    {
        $em = $ldap->getEntryManager();
        foreach ($entries as $entry) {
            $em->remove($entry);
        }
    }

    public function testLdapPaginationLimits()
    {
        $ldap = new Adapter($this->getLdapConfig());
        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');

        $entries = $this->setupTestUsers($ldap);

        $lowMaxQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 10,
            'maxItems' => 5,
        ]);
        $highMaxQuery = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 10,
            'maxItems' => 13,
        ]);

        try {
            $this->assertCount(5, $lowMaxQuery->execute());
            $this->assertCount(13, $highMaxQuery->execute());

            $this->assertCount(1, $lowMaxQuery->getResources());
            $this->assertCount(2, $highMaxQuery->getResources());
        } catch (LdapException $exc) {
            $this->markTestSkipped('Test LDAP server does not support pagination');
        }

        $this->destroyEntries($ldap, $entries);
    }
}
