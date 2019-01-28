<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Core\User\User;

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

class ConcreteToken extends AbstractToken
{
    private $credentials = 'credentials_value';

    public function __construct($user, array $roles = [])
    {
        parent::__construct($roles);

        $this->setUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        $serialized = [$this->credentials, parent::serialize(true)];

        return $this->doSerialize($serialized, \func_num_args() ? \func_get_arg(0) : null);
    }

    public function unserialize($serialized)
    {
        list($this->credentials, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }

    public function getCredentials()
    {
    }
}

/** @noinspection PhpUndefinedClassInspection */
class AbstractTokenTest extends TestCase
{
    public function testGetUsername()
    {
        $token = $this->getToken(['ROLE_FOO']);
        $token->setUser('fabien');
        $this->assertEquals('fabien', $token->getUsername());

        $token->setUser(new TestUser('fabien'));
        $this->assertEquals('fabien', $token->getUsername());

        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())->method('getUsername')->will($this->returnValue('fabien'));
        $token->setUser($user);
        $this->assertEquals('fabien', $token->getUsername());
    }

    public function testEraseCredentials()
    {
        $token = $this->getToken(['ROLE_FOO']);

        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())->method('eraseCredentials');
        $token->setUser($user);

        $token->eraseCredentials();
    }

    public function testSerialize()
    {
        $token = $this->getToken(['ROLE_FOO', new Role('ROLE_BAR')]);
        $token->setAttributes(['foo' => 'bar']);

        $uToken = unserialize(serialize($token));

        $this->assertEquals($token->getRoles(), $uToken->getRoles());
        $this->assertEquals($token->getAttributes(), $uToken->getAttributes());
    }

    public function testSerializeWithRoleObjects()
    {
        $user = new User('name', 'password', [new Role('ROLE_FOO'), new Role('ROLE_BAR')]);
        $token = new ConcreteToken($user, $user->getRoles());

        $serialized = serialize($token);
        $unserialized = unserialize($serialized);

        $roles = $unserialized->getRoles();

        $this->assertEquals($roles, $user->getRoles());
    }

    public function testSerializeParent()
    {
        $user = new TestUser('fabien');
        $token = new ConcreteToken($user, ['ROLE_FOO']);

        $parentToken = new ConcreteToken($user, [new SwitchUserRole('ROLE_PREVIOUS', $token)]);
        $uToken = unserialize(serialize($parentToken));

        $this->assertEquals(
            current($parentToken->getRoles())->getSource()->getUser(),
            current($uToken->getRoles())->getSource()->getUser()
        );
    }

    public function testConstructor()
    {
        $token = $this->getToken(['ROLE_FOO']);
        $this->assertEquals([new Role('ROLE_FOO')], $token->getRoles());

        $token = $this->getToken([new Role('ROLE_FOO')]);
        $this->assertEquals([new Role('ROLE_FOO')], $token->getRoles());

        $token = $this->getToken([new Role('ROLE_FOO'), 'ROLE_BAR']);
        $this->assertEquals([new Role('ROLE_FOO'), new Role('ROLE_BAR')], $token->getRoles());
    }

    public function testAuthenticatedFlag()
    {
        $token = $this->getToken();
        $this->assertFalse($token->isAuthenticated());

        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    public function testAttributes()
    {
        $attributes = ['foo' => 'bar'];
        $token = $this->getToken();
        $token->setAttributes($attributes);

        $this->assertEquals($attributes, $token->getAttributes(), '->getAttributes() returns the token attributes');
        $this->assertEquals('bar', $token->getAttribute('foo'), '->getAttribute() returns the value of an attribute');
        $token->setAttribute('foo', 'foo');
        $this->assertEquals('foo', $token->getAttribute('foo'), '->setAttribute() changes the value of an attribute');
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

    /**
     * @dataProvider getUsers
     */
    public function testSetUser($user)
    {
        $token = $this->getToken();
        $token->setUser($user);
        $this->assertSame($user, $token->getUser());
    }

    public function getUsers()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $advancedUser = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();

        return [
            [$advancedUser],
            [$user],
            [new TestUser('foo')],
            ['foo'],
        ];
    }

    /**
     * @dataProvider getUserChanges
     */
    public function testSetUserSetsAuthenticatedToFalseWhenUserChanges($firstUser, $secondUser)
    {
        $token = $this->getToken();
        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($firstUser);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($secondUser);
        $this->assertFalse($token->isAuthenticated());
    }

    public function getUserChanges()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $advancedUser = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();

        return [
            [
                'foo', 'bar',
            ],
            [
                'foo', new TestUser('bar'),
            ],
            [
                'foo', $user,
            ],
            [
                'foo', $advancedUser,
            ],
            [
                $user, 'foo',
            ],
            [
                $advancedUser, 'foo',
            ],
            [
                $user, new TestUser('foo'),
            ],
            [
                $advancedUser, new TestUser('foo'),
            ],
            [
                new TestUser('foo'), new TestUser('bar'),
            ],
            [
                new TestUser('foo'), 'bar',
            ],
            [
                new TestUser('foo'), $user,
            ],
            [
                new TestUser('foo'), $advancedUser,
            ],
            [
                $user, $advancedUser,
            ],
            [
                $advancedUser, $user,
            ],
        ];
    }

    /**
     * @dataProvider getUsers
     */
    public function testSetUserDoesNotSetAuthenticatedToFalseWhenUserDoesNotChange($user)
    {
        $token = $this->getToken();
        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($user);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($user);
        $this->assertTrue($token->isAuthenticated());
    }

    protected function getToken(array $roles = [])
    {
        return $this->getMockForAbstractClass('Symfony\Component\Security\Core\Authentication\Token\AbstractToken', [$roles]);
    }
}
