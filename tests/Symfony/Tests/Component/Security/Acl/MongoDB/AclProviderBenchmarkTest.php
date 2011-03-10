<?php

namespace Symfony\Tests\Component\Security\Acl\MongoDB;

use Doctrine\MongoDB\Connection;
use Symfony\Component\Security\Acl\MongoDB\AclProvider;
use Symfony\Component\Security\Acl\Domain\PermissionGrantingStrategy;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Core\Role\Role;

class AclProviderBenchmarkTest extends \PHPUnit_Framework_TestCase
{
    protected $con;
    protected $options;

    public function testFindAcls()
    {
        $this->generateTestData();
        
        // get some random test object identities from the database
        $oids = array();
        $randomKeys = array();
        for($i=0;$i<25;$i++) {
           $randomKeys[] = rand(0,$this->aclRandomKeyValue);
        }
        $criteria = array(
            'randomKey' => array('$in' => $randomKeys),
        );
        $fields = array(
            'objectIdentity' => true,
        );
        $cursor = $this->con->selectCollection($this->options['oid_table_name'])->find($criteria, $fields);
        foreach ($cursor as $oid) {
            $oids[] = new ObjectIdentity($oid['objectIdentifier'], $oid['classType']);
        }
        
        $provider = $this->getProvider();
        
        $start = microtime(true);
        $provider->findAcls($oids);
        $time = microtime(true) - $start;
        echo "Total Time: ".$time."s\n";
    }
    
    
    protected function setUp()
    {
        // comment the following line, and run only this test, if you need to benchmark
//        $this->markTestSkipped();

        if (!class_exists('Doctrine\MongoDB\Connection')) {
            $this->markTestSkipped('Doctrine2 MongoDB is required for this test');
        }
        $database = 'aclBenchmark';
        $mongo = new \Doctrine\MongoDB\Connection();
        $this->con = $mongo->selectDatabase($database);
        $this->options = $this->getOptions();
    }
    
    protected function tearDown()
    {
        $this->con = null;
    }
    
    /**
     * This generates a huge amount of test data to be used mainly for benchmarking
     * purposes, not so much for testing. That's why it's not called by default.
     */
    protected function generateTestData()
    {
        for ($i=0; $i<40000; $i++) {
            $this->generateAclHierarchy();
            echo "$i ";
        }
    }
    
    protected function generateAclHierarchy()
    {
        $root = $this->generateAcl($this->chooseObjectIdentity(), null, array());
        
        $this->generateAclLevel(rand(1, 15), $root, array($root['_id']));
    }
    
    protected function generateAclLevel($depth, $parent, $ancestors)
    {
        $level = count($ancestors);
        for ($i=0,$t=rand(1, 10); $i<$t; $i++) {
            $acl = $this->generateAcl($this->chooseObjectIdentity(), $parent, $ancestors);
            
            if ($level < $depth) {
                $this->generateAclLevel($depth, $acl, array_merge($ancestors, array($acl['_id'])));
            }
        }
    }
    
    protected function chooseObjectIdentity()
    {
        return array(
            'identifier' =>  $this->getRandomString(rand(20, 50)),
            'type'       =>  $this->getRandomString(rand(20, 100)),
        );
    }
    
    protected function generateAcl($objectIdentity, $parent, $ancestors)
    {
        static $aclRandomKeyValue = 0;  // used to retrieve random objects

        $oidCollection = $this->con->selectCollection($this->options['oid_table_name']);

        $acl = array(
            'objectIdentity' => $objectIdentity,
            'entriesInheriting' => (boolean)rand(0, 1),
            'randomKey' => $aclRandomKeyValue,
        );
        $aclRandomKeyValue++;
        if ($parent) {
            $acl['parent'] = $parent;
            $acl['ancestors'] = $ancestors;
        }

        $oidCollection->insert($acl);

        $this->generateAces($acl);

        return $acl;
    }
    
    protected function chooseSid()
    {
        if (rand(0,1)==0) {
            return array('role'=>$this->getRandomString(rand(10,20)));
        } else {
            return array(
                'username' => $this->getRandomString(rand(10,20)),
                'class' => $this->getRandomString(rand(10,20)),
            );
        }
    }

    protected function generateAces($acl)
    {
        $sids = array();
        $fieldOrder = array();

        $collection = $this->con->selectCollection($this->options['entry_table_name']);
        for ($i=0; $i<=30; $i++) {
            $query = array();

            $fieldName = rand(0, 1) ? null : $this->getRandomString(rand(10, 20));

            if (rand(0,5)!=0) {
                $query['objectIdentity'] = array(
                    '$ref' => $this->options['oid_table_name'],
                    '$id' => $acl['_id'],
                );
            }

            do {
                $sid = $this->chooseSid();
                $sidId  = implode('-', array_values($sid));
            }
            while (array_key_exists($sidId, $sids) && in_array($fieldName, $sids[$sidId], true));

            if (!isset($sids[$sidId])) {
                $sids[$sidId] = array();
            }

            $sids[$sidId][] = $fieldName;
            $query['securityIdentity'] = $sid;

            $fieldOrder[$fieldName] = array_key_exists($fieldName, $fieldOrder) ? $fieldOrder[$fieldName]+1 : 0;

            $strategy = rand(0, 2);
            if ($strategy === 0) {
                $query['grantingStrategy'] = PermissionGrantingStrategy::ALL;
            }
            else if ($strategy === 1) {
                $query['grantingStrategy'] = PermissionGrantingStrategy::ANY;
            }
            else {
                $query['grantingStrategy'] = PermissionGrantingStrategy::EQUAL;
            }

            $query['fieldName'] = $fieldName;
            $query['aceOrder'] = $fieldOrder[$fieldName];
            $query['securityIdentity'] = $sid;
            $query['mask'] = $this->generateMask();
            $query['granting'] = (boolean)rand(0,1);
            $query['auditSuccess'] = (boolean)rand(0,1);
            $query['auditFailure'] = (boolean)rand(0,1);

            $collection->insert($query);
        }
    }
    
    protected function generateMask()
    {
        $i = rand(1, 30);
        $mask = 0;
        
        while ($i <= 30) {
            $mask |= 1 << rand(0, 30);
            $i++;
        }
        
        return $mask;
    }
    
    protected function getRandomString($length, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
    {
        $s = '';
        $cLength = strlen($chars);
        
        while (strlen($s) < $length) {
            $s .= $chars[mt_rand(0, $cLength-1)];
        }
        
        return $s;
    }

    protected function getOptions()
    {
        return array(
            'oid_table_name' => 'aclObjectIdentities',
            'entry_table_name' => 'aclEntries',
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