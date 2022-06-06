<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationTrustResolverTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testIsAnonymous()
    {
        $resolver = new AuthenticationTrustResolver();
        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
        $this->assertFalse($resolver->isAnonymous(new FakeCustomToken()));
    }

    public function testIsRememberMe()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe(new FakeCustomToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
    }

    public function testisFullFledged()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged(new FakeCustomToken()));
    }

    public function testIsAuthenticated()
    {
        $resolver = new AuthenticationTrustResolver();
        $this->assertFalse($resolver->isAuthenticated(null));
        $this->assertTrue($resolver->isAuthenticated($this->getRememberMeToken()));
        $this->assertTrue($resolver->isAuthenticated(new FakeCustomToken()));
    }

    /**
     * @group legacy
     */
    public function testIsAnonymousWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous(new FakeCustomToken()));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
    }

    public function testIsRememberMeWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe(new FakeCustomToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
    }

    public function testisFullFledgedWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged(new FakeCustomToken()));
    }

    /**
     * @group legacy
     */
    public function testLegacy()
    {
        $resolver = $this->getResolver();

        $this->assertTrue($resolver->isAnonymous($this->getAnonymousToken()));
        $this->assertTrue($resolver->isAnonymous($this->getRealCustomAnonymousToken()));

        $this->assertFalse($resolver->isRememberMe($this->getAnonymousToken()));

        $this->assertFalse($resolver->isFullFledged($this->getAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged($this->getRealCustomAnonymousToken()));
    }

    protected function getAnonymousToken()
    {
        return new AnonymousToken('secret', 'anon.');
    }

    private function getRealCustomAnonymousToken()
    {
        return new class() extends AnonymousToken {
            public function __construct()
            {
            }
        };
    }

    protected function getRememberMeToken()
    {
        $user = new InMemoryUser('wouter', '', ['ROLE_USER']);

        return new RememberMeToken($user, 'main', 'secret');
    }

    protected function getResolver()
    {
        return new AuthenticationTrustResolver(
            AnonymousToken::class,
            RememberMeToken::class
        );
    }
}

class FakeCustomToken implements TokenInterface
{
    public function __serialize(): array
    {
    }

    public function serialize(): string
    {
    }

    public function __unserialize(array $data): void
    {
    }

    public function unserialize($serialized)
    {
    }

    public function __toString(): string
    {
    }

    public function getRoleNames(): array
    {
    }

    public function getCredentials()
    {
    }

    public function getUser(): UserInterface
    {
        return new InMemoryUser('wouter', '', ['ROLE_USER']);
    }

    public function setUser($user)
    {
    }

    public function getUsername(): string
    {
    }

    public function getUserIdentifier(): string
    {
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function setAuthenticated(bool $isAuthenticated)
    {
    }

    public function eraseCredentials()
    {
    }

    public function getAttributes(): array
    {
    }

    public function setAttributes(array $attributes)
    {
    }

    public function hasAttribute(string $name): bool
    {
    }

    public function getAttribute(string $name)
    {
    }

    public function setAttribute(string $name, $value)
    {
    }
}

class RealCustomRememberMeToken extends RememberMeToken
{
    public function __construct()
    {
    }
}
