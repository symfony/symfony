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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class AbstractTokenTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testLegacyGetUsername()
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $token->setUser('fabien');
        $this->assertEquals('fabien', $token->getUsername());

        $token->setUser(new TestUser('fabien'));
        $this->assertEquals('fabien', $token->getUsername());

        $legacyUser = new class() implements UserInterface {
            public function getUsername()
            {
                return 'fabien';
            }

            public function getRoles()
            {
                return [];
            }

            public function getPassword()
            {
            }

            public function getSalt()
            {
            }

            public function eraseCredentials()
            {
            }
        };
        $token->setUser($legacyUser);
        $this->assertEquals('fabien', $token->getUsername());

        $token->setUser($legacyUser);
        $this->assertEquals('fabien', $token->getUserIdentifier());
    }

    /**
     * @dataProvider provideUsers
     */
    public function testGetUserIdentifier($user, string $username)
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $token->setUser($user);
        $this->assertEquals($username, $token->getUserIdentifier());
    }

    public function provideUsers()
    {
        yield [new InMemoryUser('fabien', null), 'fabien'];
    }

    /**
     * @dataProvider provideLegacyUsers
     * @group legacy
     */
    public function testLegacyGetUserIdentifier($user, string $username)
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $token->setUser($user);
        $this->assertEquals($username, $token->getUserIdentifier());
    }

    public function provideLegacyUsers()
    {
        return [
            [new TestUser('fabien'), 'fabien'],
            ['fabien', 'fabien'],
        ];
    }

    public function testEraseCredentials()
    {
        $token = new ConcreteToken(['ROLE_FOO']);

        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())->method('eraseCredentials');
        $token->setUser($user);

        $token->eraseCredentials();
    }

    public function testSerialize()
    {
        $token = new ConcreteToken(['ROLE_FOO', 'ROLE_BAR']);
        $token->setAttributes(['foo' => 'bar']);

        $uToken = unserialize(serialize($token));

        $this->assertEquals($token->getRoleNames(), $uToken->getRoleNames());
        $this->assertEquals($token->getAttributes(), $uToken->getAttributes());
    }

    public function testConstructor()
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
    }

    /**
     * @group legacy
     */
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
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->getAttribute() throws an \InvalidArgumentException exception when the attribute does not exist');
            $this->assertEquals('This token has no "foobar" attribute.', $e->getMessage(), '->getAttribute() throws an \InvalidArgumentException exception when the attribute does not exist');
        }
    }

    /**
     * @dataProvider provideUsers
     */
    public function testSetUser($user)
    {
        $token = new ConcreteToken();
        $token->setUser($user);
        $this->assertSame($user, $token->getUser());
    }

    /**
     * @group legacy
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
        $user = $this->createMock(UserInterface::class);

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
     * @dataProvider provideUsers
     * @dataProvider provideLegacyUsers
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

    /**
     * @group legacy
     */
    public function testIsUserChangedWhenSerializing()
    {
        $token = new ConcreteToken(['ROLE_ADMIN']);
        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $user = new SerializableUser('wouter', ['ROLE_ADMIN']);
        $token->setUser($user);
        $this->assertTrue($token->isAuthenticated());

        $token = unserialize(serialize($token));
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

    public function __toString(): string
    {
        return $this->name;
    }
}

class SerializableUser implements UserInterface, \Serializable
{
    private $roles;
    private $name;

    public function __construct($name, array $roles = [])
    {
        $this->name = $name;
        $this->roles = $roles;
    }

    public function getUsername(): string
    {
        return $this->name;
    }

    public function getUserIdentifier(): string
    {
        return $this->name;
    }

    public function getPassword(): ?string
    {
        return '***';
    }

    public function getRoles(): array
    {
        if (empty($this->roles)) {
            return ['ROLE_USER'];
        }

        return $this->roles;
    }

    public function eraseCredentials()
    {
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize($serialized): void
    {
        $this->__unserialize(unserialize($serialized));
    }

    public function __serialize(): array
    {
        return ['name' => $this->name];
    }

    public function __unserialize(array $data): void
    {
        ['name' => $this->name] = $data;
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
