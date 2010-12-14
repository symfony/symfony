<?php

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;

use Symfony\Component\Security\Acl\Domain\ObjectIdentityRetrievalStrategy;

class ObjectIdentityRetrievalStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetObjectIdentityReturnsNullForInvalidDomainObject()
    {
        $strategy = new ObjectIdentityRetrievalStrategy();
        $this->assertNull($strategy->getObjectIdentity('foo'));
    }

    public function testGetObjectIdentity()
    {
        $strategy = new ObjectIdentityRetrievalStrategy();
        $domainObject = new DomainObject();
        $objectIdentity = $strategy->getObjectIdentity($domainObject);

        $this->assertEquals($domainObject->getId(), $objectIdentity->getIdentifier());
        $this->assertEquals(get_class($domainObject), $objectIdentity->getType());
    }
}

class DomainObject
{
    public function getId()
    {
        return 'foo';
    }
}