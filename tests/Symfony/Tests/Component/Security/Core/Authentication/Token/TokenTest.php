<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\Token as BaseToken;
use Symfony\Component\Security\Core\Role\Role;

class Token extends BaseToken
{
    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }
}

class TestUser
{
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->name;
    }
}

class TokenTest extends \PHPUnit_Framework_TestCase
{
    public function testMagicToString()
    {
        $token = new Token(array('ROLE_FOO'));
        $token->setUser('fabien');
        $this->assertEquals('fabien', (string) $token);

        $token->setUser(new TestUser('fabien'));
        $this->assertEquals('fabien', (string) $token);

        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user->expects($this->once())->method('getUsername')->will($this->returnValue('fabien'));

        $token->setUser($user);
        $this->assertEquals('fabien', (string) $token);
    }

    public function testEraseCredentials()
    {
        $token = new Token(array('ROLE_FOO'));

        $credentials = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $credentials->expects($this->once())->method('eraseCredentials');
        $token->setCredentials($credentials);

        $user = $this->getMock('Symfony\Component\Security\Core\User\AccountInterface');
        $user->expects($this->once())->method('eraseCredentials');
        $token->setUser($user);

        $token->eraseCredentials();
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::serialize
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::unserialize
     */
    public function testSerialize()
    {
        $token = new Token(array('ROLE_FOO'));
        $token->setAttributes(array('foo' => 'bar'));

        $this->assertEquals($token, unserialize(serialize($token)));
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::__construct
     */
    public function testConstructor()
    {
        $token = new Token(array('ROLE_FOO'));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token = new Token(array(new Role('ROLE_FOO')));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token = new Token(array(new Role('ROLE_FOO'), 'ROLE_BAR'));
        $this->assertEquals(array(new Role('ROLE_FOO'), new Role('ROLE_BAR')), $token->getRoles());
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::addRole
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::getRoles
     */
    public function testAddRole()
    {
        $token = new Token();
        $token->addRole(new Role('ROLE_FOO'));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token->addRole(new Role('ROLE_BAR'));
        $this->assertEquals(array(new Role('ROLE_FOO'), new Role('ROLE_BAR')), $token->getRoles());
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::isAuthenticated
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::setAuthenticated
     */
    public function testAuthenticatedFlag()
    {
        $token = new Token();
        $this->assertFalse($token->isAuthenticated());

        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::isImmutable
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::setImmutable
     */
    public function testImmutableFlag()
    {
        $token = new Token();
        $this->assertFalse($token->isImmutable());

        $token->setImmutable();
        $this->assertTrue($token->isImmutable());
    }

    /**
     * @expectedException \LogicException
     * @dataProvider getImmutabilityTests
     */
    public function testImmutabilityIsEnforced($setter, $value)
    {
        $token = new Token();
        $token->setImmutable(true);
        $token->$setter($value);
    }

    public function getImmutabilityTests()
    {
        return array(
            array('setUser', 'foo'),
            array('eraseCredentials', null),
            array('setAuthenticated', true),
            array('setAuthenticated', false),
            array('addRole', new Role('foo')),
            array('setRoles', array('foo', 'asdf')),
        );
    }

    /**
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::getAttributes
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::setAttributes
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::hasAttribute
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::getAttribute
     * @covers Symfony\Component\Security\Core\Authentication\Token\Token::setAttribute
     */
    public function testAttributes()
    {
        $attributes = array('foo' => 'bar');
        $token = new Token();
        $token->setAttributes($attributes);

        $this->assertEquals($attributes, $token->getAttributes(), '->getAttributes() returns the token attributes');
        $this->assertEquals('bar', $token->getAttribute('foo'), '->getAttribute() returns the value of a attribute');
        $token->setAttribute('foo', 'foo');
        $this->assertEquals('foo', $token->getAttribute('foo'), '->setAttribute() changes the value of a attribute');
        $this->assertTrue($token->hasAttribute('foo'), '->hasAttribute() returns true if the attribute is defined');
        $this->assertFalse($token->hasAttribute('oof'), '->hasAttribute() returns false if the attribute is not defined');

        try {
            $token->getAttribute('foobar');
            $this->fail('->getAttribute() throws an \InvalidArgumentException exception when the attribute does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getAttribute() throws an \InvalidArgumentException exception when the attribute does not exist');
            $this->assertEquals('This token has no "foobar" attribute.', $e->getMessage(), '->getAttribute() throws an \InvalidArgumentException exception when the attribute does not exist');
        }
    }
}
