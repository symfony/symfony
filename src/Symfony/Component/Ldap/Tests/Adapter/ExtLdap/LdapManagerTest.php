<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests;

use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Adapter\ExtLdap\Collection;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @requires extension ldap
 */
class LdapManagerTest extends LdapTestCase
{
    /** @var Adapter */
    private $adapter;

    protected function setUp()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->adapter->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
    }

    /**
     * @group functional
     */
    public function testLdapAddAndRemove()
    {
        $this->executeSearchQuery(1);

        $entry = new Entry('cn=Charles Sarrazin,dc=symfony,dc=com', array(
            'sn' => array('csarrazi'),
            'objectclass' => array(
                'inetOrgPerson',
            ),
        ));

        $em = $this->adapter->getEntryManager();
        $em->add($entry);

        $this->executeSearchQuery(2);

        $em->remove($entry);
        $this->executeSearchQuery(1);
    }

    /**
     * @group functional
     */
    public function testLdapAddInvalidEntry()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(LdapException::class);
        $this->executeSearchQuery(1);

        // The entry is missing a subject name
        $entry = new Entry('cn=Charles Sarrazin,dc=symfony,dc=com', array(
            'objectclass' => array(
                'inetOrgPerson',
            ),
        ));

        $em = $this->adapter->getEntryManager();
        $em->add($entry);
    }

    /**
     * @group functional
     */
    public function testLdapUpdate()
    {
        $result = $this->executeSearchQuery(1);

        $entry = $result[0];
        $this->assertNull($entry->getAttribute('email'));

        $em = $this->adapter->getEntryManager();
        $em->update($entry);

        $result = $this->executeSearchQuery(1);

        $entry = $result[0];
        $this->assertNull($entry->getAttribute('email'));

        $entry->removeAttribute('email');
        $em->update($entry);

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];
        $this->assertNull($entry->getAttribute('email'));
    }

    /**
     * @group functional
     */
    public function testLdapUnboundAdd()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(NotBoundException::class);
        $em = $this->adapter->getEntryManager();
        $em->add(new Entry(''));
    }

    /**
     * @group functional
     */
    public function testLdapUnboundRemove()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(NotBoundException::class);
        $em = $this->adapter->getEntryManager();
        $em->remove(new Entry(''));
    }

    /**
     * @group functional
     */
    public function testLdapUnboundUpdate()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(NotBoundException::class);
        $em = $this->adapter->getEntryManager();
        $em->update(new Entry(''));
    }

    /**
     * @return Collection|Entry[]
     */
    private function executeSearchQuery($expectedResults = 1)
    {
        $results = $this
            ->adapter
            ->createQuery('dc=symfony,dc=com', '(objectclass=person)')
            ->execute()
        ;

        $this->assertCount($expectedResults, $results);

        return $results;
    }

    /**
     * @group functional
     */
    public function testLdapRename()
    {
        $result = $this->executeSearchQuery(1);

        $entry = $result[0];

        $entryManager = $this->adapter->getEntryManager();
        $entryManager->rename($entry, 'cn=Kevin');

        $result = $this->executeSearchQuery(1);
        $renamedEntry = $result[0];
        $this->assertEquals($renamedEntry->getAttribute('cn')[0], 'Kevin');

        $oldRdn = $entry->getAttribute('cn')[0];
        $entryManager->rename($renamedEntry, 'cn='.$oldRdn);
        $this->executeSearchQuery(1);
    }

    /**
     * @group functional
     */
    public function testLdapRenameWithoutRemovingOldRdn()
    {
        $result = $this->executeSearchQuery(1);

        $entry = $result[0];

        $entryManager = $this->adapter->getEntryManager();
        $entryManager->rename($entry, 'cn=Kevin', false);

        $result = $this->executeSearchQuery(1);

        $newEntry = $result[0];
        $originalCN = $entry->getAttribute('cn')[0];

        $this->assertContains($originalCN, $newEntry->getAttribute('cn'));

        $entryManager->rename($newEntry, 'cn='.$originalCN);

        $this->executeSearchQuery(1);
    }
}
