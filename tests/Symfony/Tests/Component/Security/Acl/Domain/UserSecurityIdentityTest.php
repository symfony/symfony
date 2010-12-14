<?php

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;

use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

class UserSecurityIdentityTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $id = new UserSecurityIdentity('foo');
        
        $this->assertEquals('foo', $id->getUsername());
    }
    
    public function testConstructorWithToken()
    {
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('foo'))
        ;
        
        $id = new UserSecurityIdentity($token);
        
        $this->assertEquals('foo', $id->getUsername());
    }
    
    /**
     * @dataProvider getCompareData
     */
    public function testEquals($id1, $id2, $equal)
    {
        if ($equal) {
            $this->assertTrue($id1->equals($id2));
        }
        else {
            $this->assertFalse($id1->equals($id2));
        }
    }
    
    public function getCompareData()
    {
        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('foo'))
        ;
        
        return array(
            array(new UserSecurityIdentity('foo'), new UserSecurityIdentity('foo'), true),
            array(new UserSecurityIdentity('foo'), new UserSecurityIdentity($token), true),
            array(new UserSecurityIdentity('bla'), new UserSecurityIdentity('blub'), false),
            array(new UserSecurityIdentity('foo'), new RoleSecurityIdentity('foo'), false),
        );
    }
}