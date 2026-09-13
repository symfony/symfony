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
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthenticationTrustResolverTest extends TestCase
{
    public function testIsRememberMe()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe(new FakeCustomToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
    }

    public function testIsAuthenticatedRecently()
    {
        $clock = new MockClock('2026-09-13 12:00:00');
        $resolver = new AuthenticationTrustResolver(900, $clock);

        $this->assertFalse($resolver->isAuthenticatedRecently(null));

        // a token that never went through an interactive login carries no stamp
        $this->assertFalse($resolver->isAuthenticatedRecently($this->getUsernamePasswordToken()));

        $fresh = $this->getUsernamePasswordToken();
        $fresh->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, $clock->now()->getTimestamp());
        $this->assertTrue($resolver->isAuthenticatedRecently($fresh));

        $clock->sleep(900);
        $this->assertTrue($resolver->isAuthenticatedRecently($fresh));
        $clock->sleep(1);
        $this->assertFalse($resolver->isAuthenticatedRecently($fresh));
    }

    private function getUsernamePasswordToken(): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser('wouter', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']);
    }

    public function testRememberMeIsNeverAuthenticatedRecently()
    {
        $resolver = new AuthenticationTrustResolver();

        $token = $this->getRememberMeToken();
        $token->setAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE, time());

        $this->assertFalse($resolver->isAuthenticatedRecently($token));
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

    public function testIsRememberMeWithClassAsConstructorButStillExtending()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe(new FakeCustomToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
    }

    public function testisFullFledgedWithClassAsConstructorButStillExtending()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged(new FakeCustomToken()));
    }

    protected function getRememberMeToken()
    {
        $user = new InMemoryUser('wouter', '', ['ROLE_USER']);

        return new RememberMeToken($user, 'main');
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

    public function getCredentials(): mixed
    {
    }

    public function getUser(): UserInterface
    {
        return new InMemoryUser('wouter', '', ['ROLE_USER']);
    }

    public function setUser($user): void
    {
    }

    public function getUserIdentifier(): string
    {
    }

    public function getAttributes(): array
    {
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function hasAttribute(string $name): bool
    {
    }

    public function getAttribute(string $name): mixed
    {
    }

    public function setAttribute(string $name, $value): void
    {
    }
}

class RealCustomRememberMeToken extends RememberMeToken
{
    public function __construct()
    {
    }
}
