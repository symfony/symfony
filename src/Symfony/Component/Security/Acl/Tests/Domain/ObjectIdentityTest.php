<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Tests\Domain
{
    use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
    use Symfony\Component\Security\Acl\Model\DomainObjectInterface;

    class ObjectIdentityTest extends \PHPUnit_Framework_TestCase
    {
        public function testConstructor()
        {
            $id = new ObjectIdentity('fooid', 'footype');

            $this->assertEquals('fooid', $id->getIdentifier());
            $this->assertEquals('footype', $id->getType());
        }

        // Test that constructor never changes passed type, even with proxies
        public function testConstructorWithProxy()
        {
            $id = new ObjectIdentity('fooid', 'Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject');

            $this->assertEquals('fooid', $id->getIdentifier());
            $this->assertEquals('Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject', $id->getType());
        }

        public function testFromDomainObjectPrefersInterfaceOverGetId()
        {
            $domainObject = new DomainObjectImplementation();

            $id = ObjectIdentity::fromDomainObject($domainObject);
            $this->assertEquals('getObjectIdentifier()', $id->getIdentifier());
        }

        public function testFromDomainObjectWithoutInterface()
        {
            $id = ObjectIdentity::fromDomainObject(new TestDomainObject());
            $this->assertEquals('getId()', $id->getIdentifier());
            $this->assertEquals('Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject', $id->getType());
        }

        public function testFromDomainObjectWithProxy()
        {
            $id = ObjectIdentity::fromDomainObject(new \Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject());
            $this->assertEquals('getId()', $id->getIdentifier());
            $this->assertEquals('Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject', $id->getType());
        }

        public function testFromDomainObjectWithoutInterfaceEnforcesStringIdentifier()
        {
            $domainObject = new TestDomainObject();
            $domainObject->id = 1;
            $id = ObjectIdentity::fromDomainObject($domainObject);

            $this->assertSame('1', $id->getIdentifier());
            $this->assertEquals('Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject', $id->getType());
        }

        public function testFromDomainObjectWithoutInterfaceAllowsZeroAsIdentifier()
        {
            $domainObject = new TestDomainObject();
            $domainObject->id = '0';
            $id = ObjectIdentity::fromDomainObject($domainObject);

            $this->assertSame('0', $id->getIdentifier());
            $this->assertEquals('Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject', $id->getType());
        }

        /**
         * @dataProvider getCompareData
         */
        public function testEquals($oid1, $oid2, $equal)
        {
            if ($equal) {
                $this->assertTrue($oid1->equals($oid2));
            } else {
                $this->assertFalse($oid1->equals($oid2));
            }
        }

        public function getCompareData()
        {
            return array(
                array(new ObjectIdentity('123', 'foo'), new ObjectIdentity('123', 'foo'), true),
                array(new ObjectIdentity('123', 'foo'), new ObjectIdentity(123, 'foo'), true),
                array(new ObjectIdentity('1', 'foo'), new ObjectIdentity('2', 'foo'), false),
                array(new ObjectIdentity('1', 'bla'), new ObjectIdentity('1', 'blub'), false),
            );
        }
    }

    class TestDomainObject
    {
        public $id = 'getId()';

        public function getObjectIdentifier()
        {
            return 'getObjectIdentifier()';
        }

        public function getId()
        {
            return $this->id;
        }
    }

    class DomainObjectImplementation implements DomainObjectInterface
    {
        public function getObjectIdentifier()
        {
            return 'getObjectIdentifier()';
        }

        public function getId()
        {
            return 'getId()';
        }
    }
}

namespace Acme\DemoBundle\Proxy\__CG__\Symfony\Component\Security\Acl\Tests\Domain
{
    class TestDomainObject extends \Symfony\Component\Security\Acl\Tests\Domain\TestDomainObject
    {
    }
}
