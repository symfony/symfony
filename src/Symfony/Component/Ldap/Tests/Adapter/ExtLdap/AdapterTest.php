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

        $unpaged_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
        ]);
        $fully_paged_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 25,
        ]);
        $paged_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 5,
        ]);

        try {
            $unpaged_results = $unpaged_query->execute();
            $fully_paged_results = $fully_paged_query->execute();
            $paged_results = $paged_query->execute();

            // All four of the above queries should result in the 25 'users' being returned
            $this->assertEquals($unpaged_results->count(), 25);
            $this->assertEquals($fully_paged_results->count(), 25);
            $this->assertEquals($paged_results->count(), 25);

            // They should also result in 1 or 25 / pageSize results
            $this->assertEquals(\count($unpaged_query->getResources()), 1);
            $this->assertEquals(\count($fully_paged_query->getResources()), 1);
            $this->assertEquals(\count($paged_query->getResources()), 5);

            // This last query is to ensure that we haven't botched the state of our connection
            // by not resetting pagination properly.
            $final_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
                'scope' => Query::SCOPE_ONE,
            ]);

            $final_results = $final_query->execute();

            $this->assertEquals($final_results->count(), 25);
            $this->assertEquals(\count($final_query->getResources()), 1);
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
            $cn = sprintf('user%d', $i);
            $entry = new Entry(sprintf('cn=%s,dc=symfony,dc=com', $cn));
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

        $low_max_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 10,
            'maxItems' => 5,
        ]);
        $high_max_query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectClass=applicationProcess)(cn=user*))', [
            'scope' => Query::SCOPE_ONE,
            'pageSize' => 10,
            'maxItems' => 13,
        ]);

        try {
            $low_max_results = $low_max_query->execute();
            $high_max_results = $high_max_query->execute();

            $this->assertEquals($low_max_results->count(), 5);
            $this->assertEquals($high_max_results->count(), 13);

            $this->assertEquals(\count($low_max_query->getResources()), 1);
            $this->assertEquals(\count($high_max_query->getResources()), 2);
        } catch (LdapException $exc) {
            $this->markTestSkipped('Test LDAP server does not support pagination');
        }

        $this->destroyEntries($ldap, $entries);
    }
}
