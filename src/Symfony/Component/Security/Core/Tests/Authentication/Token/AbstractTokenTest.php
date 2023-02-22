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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class AbstractTokenTest extends TestCase
{
    /**
     * @dataProvider provideUsers
     */
    public function testGetUserIdentifier($user, string $username)
    {
        $token = new ConcreteToken(['ROLE_FOO']);
        $token->setUser($user);
        $this->assertEquals($username, $token->getUserIdentifier());
    }

    public static function provideUsers()
    {
        yield [new InMemoryUser('fabien', null), 'fabien'];
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

    public function getCredentials(): mixed
    {
    }
}
