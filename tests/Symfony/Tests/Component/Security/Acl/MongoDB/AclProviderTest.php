<?php

namespace Symfony\Tests\Component\Security\Acl\MongoDB;

use Symfony\Component\Security\Acl\MongoDB\AclProvider;
use Symfony\Component\Security\Acl\Domain\PermissionGrantingStrategy;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Doctrine\MongoDB\Connection;

class AclProviderTest extends \PHPUnit_Framework_TestCase
{
    protected $con;
    protected $classCollection;
    protected $entryCollection;
    protected $oidCollection;
    protected $oidAncestorCollection;
    protected $sidCollection;

    /**
     * @expectedException Symfony\Component\Security\Acl\Exception\AclNotFoundException
     * @expectedMessage There is no ACL for the given object identity.
     */
    public function testFindAclThrowsExceptionWhenNoAclExists()
    {
        $this->getProvider()->findAcl(new ObjectIdentity('foo', 'foo'));
    }

    public function testFindAclsThrowsExceptionUnlessAnACLIsFoundForEveryOID()
    {
        $oids = array();
        $oids[] = new ObjectIdentity('1', 'foo');
        $oids[] = new ObjectIdentity('foo', 'foo');

        try {
            $this->getProvider()->findAcls($oids);

            $this->fail('Provider did not throw an expected exception.');
        } catch (\Exception $ex) {
            $this->assertInstanceOf('Symfony\Component\Security\Acl\Exception\AclNotFoundException', $ex);
            $this->assertInstanceOf('Symfony\Component\Security\Acl\Exception\NotAllAclsFoundException', $ex);

            $partialResult = $ex->getPartialResult();
            $this->assertTrue($partialResult->contains($oids[0]));
            $this->assertFalse($partialResult->contains($oids[1]));
        }
    }

    public function testFindAcls()
    {
        $oids = array();
        $oids[] = new ObjectIdentity('1', 'foo');
        $oids[] = new ObjectIdentity('2', 'foo');

        $provider = $this->getProvider();

        $acls = $provider->findAcls($oids);
        $this->assertInstanceOf('SplObjectStorage', $acls);
        $this->assertEquals(2, count($acls));
        $this->assertInstanceOf('Symfony\Component\Security\Acl\Domain\Acl', $acl0 = $acls->offsetGet($oids[0]));
        $this->assertInstanceOf('Symfony\Component\Security\Acl\Domain\Acl', $acl1 = $acls->offsetGet($oids[1]));
        $this->assertTrue($oids[0]->equals($acl0->getObjectIdentity()));
        $this->assertTrue($oids[1]->equals($acl1->getObjectIdentity()));
    }

    public function testFindAclCachesAclInMemory()
    {
        $oid = new ObjectIdentity('1', 'foo');
        $provider = $this->getProvider();

        $acl = $provider->findAcl($oid);
        $this->assertSame($acl, $cAcl = $provider->findAcl($oid));

        $cAces = $cAcl->getObjectAces();
        foreach ($acl->getObjectAces() as $index => $ace) {
            $this->assertSame($ace, $cAces[$index]);
        }
    }

    public function testFindAcl()
    {
        $oid = new ObjectIdentity('1', 'foo');
        $provider = $this->getProvider();

        $acl = $provider->findAcl($oid);

        $this->assertInstanceOf('Symfony\Component\Security\Acl\Domain\Acl', $acl);
        $this->assertTrue($oid->equals($acl->getObjectIdentity()));
        $this->assertEquals(4, $acl->getId());
        $this->assertEquals(0, count($acl->getClassAces()));
        $this->assertEquals(0, count($this->getField($acl, 'classFieldAces')));
        $this->assertEquals(3, count($acl->getObjectAces()));
        $this->assertEquals(0, count($this->getField($acl, 'objectFieldAces')));

        $aces = $acl->getObjectAces();
        $this->assertInstanceOf('Symfony\Component\Security\Acl\Domain\Entry', $aces[0]);
        $this->assertTrue($aces[0]->isGranting());
        $this->assertTrue($aces[0]->isAuditSuccess());
        $this->assertTrue($aces[0]->isAuditFailure());
        $this->assertEquals('all', $aces[0]->getStrategy());
        $this->assertSame(2, $aces[0]->getMask());

        // check ACE are in correct order
        $i = 0;
        foreach ($aces as $index => $ace) {
            $this->assertEquals($i, $index);
            $i++;
        }

        $sid = $aces[0]->getSecurityIdentity();
        $this->assertInstanceOf('Symfony\Component\Security\Acl\Domain\UserSecurityIdentity', $sid);
        $this->assertEquals('john.doe', $sid->getUsername());
        $this->assertEquals('SomeClass', $sid->getClass());
    }

    protected function setUp()
    {
        if (!class_exists('Doctrine\MongoDB\Connection')) {
            $this->markTestSkipped('Doctrine2 MongoDB is required for this test');
        }
        $database = 'aclTest';
        $mongo = new \Doctrine\MongoDB\Connection();
        $this->con = $mongo->selectDatabase($database);

        $options = $this->getOptions();

        // populate the db with some test data
        $this->classCollection = $this->con->selectCollection($options['class_table_name']);
        $fields = array('class_type');
        $classes = array();
        foreach ($this->getClassData() as $data) {
            $id = array_shift($data);
            $query = array_combine($fields, $data);
            $this->classCollection->insert($query);
            $classes[$id] = $query;
        }

        $this->sidCollection = $this->con->selectCollection($options['sid_table_name']);
        $fields = array('identifier', 'username');
        $sids = array();
        foreach ($this->getSidData() as $data) {
            $id = array_shift($data);
            $query = array_combine($fields, $data);
            $this->sidCollection->insert($query);
            $sids[$id] = $query;
        }

        $this->oidCollection = $this->con->selectCollection($options['oid_table_name']);
        $oids = array();
        foreach ($this->getOidData() as $data) {
            $query = array();
            $id = $data[0];
            $classId = $data[1];
            $query['class'] = $classes[$classId];
            $query['object_identifier'] = $data[2];
            $parentId = $data[3];
            if($parentId) {
                $parent = $oids[$parentId];
                if( isset($parent['ancestors'])) {
                  $ancestors = $parent['ancestors'];
                }
                $ancestors[] = $parent['_id'];
                $query['ancestors'] = $ancestors;
                $query['parent'] = $parent;
            }
            $query['entries_inheriting'] = $data[4];
            $this->oidCollection->insert($query);
            $oids[$id] = $query;
        }
        
        $fields = array('id', 'class', 'object_identity', 'field_name', 'ace_order', 'security_identity', 'mask', 'granting', 'granting_strategy', 'audit_success', 'audit_failure');
        $this->entryCollection = $this->con->selectCollection($options['entry_table_name']);
        foreach ($this->getEntryData() as $data) {
            $query = array_combine($fields, $data);
            $classId = $query['class'];
            $query['class'] = $classes[$classId];
            $oid = $query['object_identity'];
            $query['object_identity'] = $oids[$oid];
            $sid = $query['security_identity'];
            $query['security_identity'] = $sids[$sid];
            $this->entryCollection->insert($query);
        }
    }

    protected function tearDown()
    {
        $this->con->drop();
        $this->con = null;
    }

    protected function getField($object, $field)
    {
        $reflection = new \ReflectionProperty($object, $field);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    protected function getEntryData()
    {
        // id, cid, oid, field, order, sid, mask, granting, strategy, a success, a failure
        return array(
            array(1, 1, 1, null, 0, 1, 1, 1, 'all', 1, 1),
            array(2, 1, 1, null, 1, 2, 1 << 2 | 1 << 1, 0, 'any', 0, 0),
            array(3, 3, 4, null, 0, 1, 2, 1, 'all', 1, 1),
            array(4, 3, 4, null, 2, 2, 1, 1, 'all', 1, 1),
            array(5, 3, 4, null, 1, 3, 1, 1, 'all', 1, 1),
        );
    }

    protected function getOidData()
    {
        // id, cid, oid, parent_oid, entries_inheriting
        return array(
            array(1, 1, '123', null, 1),
            array(2, 2, '123', 1, 1),
            array(3, 2, 'i:3:123', 1, 1),
            array(4, 3, '1', 2, 1),
            array(5, 3, '2', 2, 1),
        );
    }

    protected function getSidData()
    {
        return array(
            array(1, 'SomeClass-john.doe', 1),
            array(2, 'MyClass-john.doe@foo.com', 1),
            array(3, 'FooClass-123', 1),
            array(4, 'MooClass-ROLE_USER', 1),
            array(5, 'ROLE_USER', 0),
            array(6, 'IS_AUTHENTICATED_FULLY', 0),
        );
    }

    protected function getClassData()
    {
        return array(
            array(1, 'Bundle\SomeVendor\MyBundle\Entity\SomeEntity'),
            array(2, 'Bundle\MyBundle\Entity\AnotherEntity'),
            array(3, 'foo'),
        );
    }

    protected function getOptions()
    {
        return array(
            'oid_table_name' => 'acl_object_identities',
            'oid_ancestors_table_name' => 'acl_object_identity_ancestors',
            'class_table_name' => 'acl_classes',
            'sid_table_name' => 'acl_security_identities',
            'entry_table_name' => 'acl_entries',
        );
    }

    protected function getStrategy()
    {
        return new PermissionGrantingStrategy();
    }

    protected function getProvider()
    {
        return new AclProvider($this->con, $this->getStrategy(), $this->getOptions());
    }
}