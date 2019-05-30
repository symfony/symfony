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
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class AbstractTokenTest extends TestCase
{
    public function testGetUsername()
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $token->setUser('fabien');
        $this->assertEquals('fabien', $token->getUsername());

        $token->setUser(new TestUser('fabien'));
        $this->assertEquals('fabien', $token->getUsername());

        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())->method('getUsername')->willReturn('fabien');
        $token->setUser($user);
        $this->assertEquals('fabien', $token->getUsername());
    }

    public function testEraseCredentials()
    {
        $token = new ConcreteToken(['ROLE_FOO']);

        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->once())->method('eraseCredentials');
        $token->setUser($user);

        $token->eraseCredentials();
    }

    public function testSerialize()
    {
        $token = new ConcreteToken(['ROLE_FOO', new Role('ROLE_BAR', false)]);
        $token->setAttributes(['foo' => 'bar']);

        $uToken = unserialize(serialize($token));

        $this->assertEquals($token->getRoleNames(), $uToken->getRoleNames());
        $this->assertEquals($token->getAttributes(), $uToken->getAttributes());
    }

    /**
     * @group legacy
     */
    public function testSerializeWithRoleObjects()
    {
        $user = new User('name', 'password', [new Role('ROLE_FOO'), new Role('ROLE_BAR')]);
        $token = new ConcreteToken($user->getRoles(), $user);

        $serialized = serialize($token);
        $unserialized = unserialize($serialized);

        $roles = $unserialized->getRoles();

        $this->assertEquals($roles, $user->getRoles());
    }

    public function testConstructor()
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
    }

    /**
     * @group legacy
     */
    public function testConstructorWithRoleObjects()
    {
        $token = new ConcreteToken([new Role('ROLE_FOO')]);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());

        $token = new ConcreteToken([new Role('ROLE_FOO'), 'ROLE_BAR']);
        $this->assertEquals(['ROLE_FOO', 'ROLE_BAR'], $token->getRoleNames());
    }

    /**
     * @group legacy
     */
    public function testGetRoles()
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $this->assertEquals([new Role('ROLE_FOO')], $token->getRoles());

        $token = new ConcreteToken([new Role('ROLE_FOO')]);
        $this->assertEquals([new Role('ROLE_FOO')], $token->getRoles());

        $token = new ConcreteToken([new Role('ROLE_FOO'), 'ROLE_BAR']);
        $this->assertEquals([new Role('ROLE_FOO'), new Role('ROLE_BAR')], $token->getRoles());
    }

    public function testAuthenticatedFlag()
    {
        $token = new ConcreteToken();
        $this->assertFalse($token->isAuthenticated());

        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    public function testAttributes()
    {
        $attributes = ['foo' => 'bar'];
        $token = new ConcreteToken();
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
        $token = new ConcreteToken();
        $token->setUser($user);
        $this->assertSame($user, $token->getUser());
    }

    public function getUsers()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();

        return [
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
        $token = new ConcreteToken();
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

        return [
            ['foo', 'bar'],
            ['foo', new TestUser('bar')],
            ['foo', $user],
            [$user, 'foo'],
            [$user, new TestUser('foo')],
            [new TestUser('foo'), new TestUser('bar')],
            [new TestUser('foo'), 'bar'],
            [new TestUser('foo'), $user],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider getUserChangesAdvancedUser
     */
    public function testSetUserSetsAuthenticatedToFalseWhenUserChangesAdvancedUser($firstUser, $secondUser)
    {
        $token = new ConcreteToken();
        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($firstUser);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($secondUser);
        $this->assertFalse($token->isAuthenticated());
    }

    public function getUserChangesAdvancedUser()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $advancedUser = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();

        return [
            ['foo', 'bar'],
            ['foo', new TestUser('bar')],
            ['foo', $user],
            ['foo', $advancedUser],
            [$user, 'foo'],
            [$advancedUser, 'foo'],
            [$user, new TestUser('foo')],
            [$advancedUser, new TestUser('foo')],
            [new TestUser('foo'), new TestUser('bar')],
            [new TestUser('foo'), 'bar'],
            [new TestUser('foo'), $user],
            [new TestUser('foo'), $advancedUser],
            [$user, $advancedUser],
            [$advancedUser, $user],
        ];
    }

    /**
     * @dataProvider getUsers
     */
    public function testSetUserDoesNotSetAuthenticatedToFalseWhenUserDoesNotChange($user)
    {
        $token = new ConcreteToken();
        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($user);
        $this->assertTrue($token->isAuthenticated());

        $token->setUser($user);
        $this->assertTrue($token->isAuthenticated());
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

class ConcreteToken extends AbstractToken
{
    private $credentials = 'credentials_value';

    public function __construct(array $roles = [], UserInterface $user = null)
    {
        parent::__construct($roles);

        if (null !== $user) {
            $this->setUser($user);
        }
    }

    public function __serialize(): array
    {
        return [$this->credentials, parent::__serialize()];
    }

    public function __unserialize(array $data): void
    {
        [$this->credentials, $parentState] = $data;
        parent::__unserialize($parentState);
    }

    public function getCredentials()
    {
    }
}
