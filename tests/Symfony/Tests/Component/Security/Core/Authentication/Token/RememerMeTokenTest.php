<?php

namespace Symfony\Tests\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Role\Role;

class RememberMeTokenTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $user = $this->getUser();
        $token = new RememberMeToken($user, 'fookey', 'foo');

        $this->assertEquals('fookey', $token->getProviderKey());
        $this->assertEquals('foo', $token->getKey());
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());
        $this->assertSame($user, $token->getUser());
        $this->assertTrue($token->isAuthenticated());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorKeyCannotBeNull()
    {
        new RememberMeToken(
            $this->getUser(),
            null,
            null
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorKeyCannotBeEmptyString()
    {
        new RememberMeToken(
            $this->getUser(),
            '',
            ''
        );
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     * @dataProvider getUserArguments
     */
    public function testConstructorUserCannotBeNull($user)
    {
        new RememberMeToken($user, 'foo', 'foo');
    }

    public function getUserArguments()
    {
        return array(
            array(null),
            array('foo'),
        );
    }

    public function testPersistentToken()
    {
        $token = new RememberMeToken($this->getUser(), 'fookey', 'foo');
        $persistentToken = $this->getMock('Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface');

        $this->assertNull($token->getPersistentToken());
        $token->setPersistentToken($persistentToken);
        $this->assertSame($persistentToken, $token->getPersistentToken());
    }

    protected function getUser($roles = array('ROLE_FOO'))
    {
        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue($roles))
        ;

        return $user;
    }
}