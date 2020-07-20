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
use Symfony\Component\Ldap\Adapter\ExtLdap\UpdateOperation;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\AlreadyExistsException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\Exception\UpdateOperationException;
use Symfony\Component\Ldap\Tests\LdapTestCase;

/**
 * @requires extension ldap
 */
class LdapManagerTest extends LdapTestCase
{
    /** @var Adapter */
    private $adapter;

    protected function setUp(): void
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

        $entry = new Entry('cn=Charles Sarrazin,dc=symfony,dc=com', [
            'sn' => ['csarrazi'],
            'objectclass' => [
                'inetOrgPerson',
            ],
        ]);

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
        $this->expectException(LdapException::class);
        $this->executeSearchQuery(1);

        // The entry is missing a subject name
        $entry = new Entry('cn=Charles Sarrazin,dc=symfony,dc=com', [
            'objectclass' => [
                'inetOrgPerson',
            ],
        ]);

        $em = $this->adapter->getEntryManager();
        $em->add($entry);
    }

    /**
     * @group functional
     */
    public function testLdapAddDouble()
    {
        $this->expectException(AlreadyExistsException::class);
        $this->executeSearchQuery(1);

        $entry = new Entry('cn=Elsa Amrouche,dc=symfony,dc=com', [
            'sn' => ['eamrouche'],
            'objectclass' => [
                'inetOrgPerson',
            ],
        ]);

        $em = $this->adapter->getEntryManager();
        $em->add($entry);
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
        $this->expectException(NotBoundException::class);
        $em = $this->adapter->getEntryManager();
        $em->add(new Entry(''));
    }

    /**
     * @group functional
     */
    public function testLdapUnboundRemove()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->expectException(NotBoundException::class);
        $em = $this->adapter->getEntryManager();
        $em->remove(new Entry(''));
    }

    /**
     * @group functional
     */
    public function testLdapUnboundUpdate()
    {
        $this->adapter = new Adapter($this->getLdapConfig());
        $this->expectException(NotBoundException::class);
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

        $this->assertStringContainsString($originalCN, $newEntry->getAttribute('cn'));

        $entryManager->rename($newEntry, 'cn='.$originalCN);

        $this->executeSearchQuery(1);
    }

    public function testLdapAddRemoveAttributeValues()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $entryManager->addAttributeValues($entry, 'mail', ['fabpot@example.org', 'fabpot2@example.org']);

        $result = $this->executeSearchQuery(1);
        $newEntry = $result[0];

        $this->assertCount(4, $newEntry->getAttribute('mail'));

        $entryManager->removeAttributeValues($newEntry, 'mail', ['fabpot@example.org', 'fabpot2@example.org']);

        $result = $this->executeSearchQuery(1);
        $newNewEntry = $result[0];

        $this->assertCount(2, $newNewEntry->getAttribute('mail'));
    }

    public function testLdapRemoveAttributeValuesError()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $this->expectException(LdapException::class);

        $entryManager->removeAttributeValues($entry, 'mail', ['fabpot@example.org']);
    }

    public function testLdapAddAttributeValuesError()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $this->expectException(LdapException::class);

        $entryManager->addAttributeValues($entry, 'mail', $entry->getAttribute('mail'));
    }

    public function testLdapApplyOperationsRemoveAllWithArrayError()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $this->expectException(UpdateOperationException::class);

        $entryManager->applyOperations($entry->getDn(), [new UpdateOperation(LDAP_MODIFY_BATCH_REMOVE_ALL, 'mail', [])]);
    }

    public function testLdapApplyOperationsWithWrongConstantError()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $this->expectException(UpdateOperationException::class);

        $entryManager->applyOperations($entry->getDn(), [new UpdateOperation(512, 'mail', [])]);
    }

    public function testApplyOperationsAddRemoveAttributeValues()
    {
        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $entryManager->applyOperations($entry->getDn(), [
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot@example.org', 'fabpot2@example.org']),
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot3@example.org', 'fabpot4@example.org']),
        ]);

        $result = $this->executeSearchQuery(1);
        $newEntry = $result[0];

        $this->assertCount(6, $newEntry->getAttribute('mail'));

        $entryManager->applyOperations($entry->getDn(), [
            new UpdateOperation(LDAP_MODIFY_BATCH_REMOVE, 'mail', ['fabpot@example.org', 'fabpot2@example.org']),
            new UpdateOperation(LDAP_MODIFY_BATCH_REMOVE, 'mail', ['fabpot3@example.org', 'fabpot4@example.org']),
        ]);

        $result = $this->executeSearchQuery(1);
        $newNewEntry = $result[0];

        $this->assertCount(2, $newNewEntry->getAttribute('mail'));
    }

    public function testUpdateOperationsWithIterator()
    {
        $iteratorAdd = new \ArrayIterator([
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot@example.org', 'fabpot2@example.org']),
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot3@example.org', 'fabpot4@example.org']),
        ]);

        $iteratorRemove = new \ArrayIterator([
            new UpdateOperation(LDAP_MODIFY_BATCH_REMOVE, 'mail', ['fabpot@example.org', 'fabpot2@example.org']),
            new UpdateOperation(LDAP_MODIFY_BATCH_REMOVE, 'mail', ['fabpot3@example.org', 'fabpot4@example.org']),
        ]);

        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $entryManager->applyOperations($entry->getDn(), $iteratorAdd);

        $result = $this->executeSearchQuery(1);
        $newEntry = $result[0];

        $this->assertCount(6, $newEntry->getAttribute('mail'));

        $entryManager->applyOperations($entry->getDn(), $iteratorRemove);

        $result = $this->executeSearchQuery(1);
        $newNewEntry = $result[0];

        $this->assertCount(2, $newNewEntry->getAttribute('mail'));
    }

    public function testUpdateOperationsThrowsExceptionWhenAddedDuplicatedValue()
    {
        $duplicateIterator = new \ArrayIterator([
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot@example.org']),
            new UpdateOperation(LDAP_MODIFY_BATCH_ADD, 'mail', ['fabpot@example.org']),
        ]);

        $entryManager = $this->adapter->getEntryManager();

        $result = $this->executeSearchQuery(1);
        $entry = $result[0];

        $this->expectException(UpdateOperationException::class);

        $entryManager->applyOperations($entry->getDn(), $duplicateIterator);
    }

    /**
     * @group functional
     */
    public function testLdapMove()
    {
        $result = $this->executeSearchQuery(1);

        $entry = $result[0];
        $this->assertNotContains('ou=Ldap', $entry->getDn());

        $entryManager = $this->adapter->getEntryManager();
        $entryManager->move($entry, 'ou=Ldap,ou=Components,dc=symfony,dc=com');

        $result = $this->executeSearchQuery(1);
        $movedEntry = $result[0];
        $this->assertStringContainsString('ou=Ldap', $movedEntry->getDn());
    }
}
