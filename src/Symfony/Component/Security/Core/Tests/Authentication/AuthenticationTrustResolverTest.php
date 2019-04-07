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

class AuthenticationTrustResolverTest extends TestCase
{
    public function testIsAnonymous()
    {
        $resolver = new AuthenticationTrustResolver();
        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous($this->getToken()));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
        $this->assertFalse($resolver->isAnonymous(new FakeCustomToken()));
        $this->assertTrue($resolver->isAnonymous(new RealCustomAnonymousToken()));
        $this->assertTrue($resolver->isAnonymous($this->getAnonymousToken()));
    }

    public function testIsRememberMe()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe($this->getToken()));
        $this->assertFalse($resolver->isRememberMe($this->getAnonymousToken()));
        $this->assertFalse($resolver->isRememberMe(new FakeCustomToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
    }

    public function testisFullFledged()
    {
        $resolver = new AuthenticationTrustResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged($this->getToken()));
        $this->assertTrue($resolver->isFullFledged(new FakeCustomToken()));
    }

    /**
     * @group legacy
     * @expectedDeprecation Configuring a custom anonymous token class is deprecated since Symfony 4.2; have the "Symfony\Component\Security\Core\Tests\Authentication\FakeCustomToken" class extend the "Symfony\Component\Security\Core\Authentication\Token\AnonymousToken" class instead, and remove the "Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver" constructor argument.
     */
    public function testsAnonymousDeprecationWithCustomClasses()
    {
        $resolver = new AuthenticationTrustResolver(FakeCustomToken::class);

        $this->assertTrue($resolver->isAnonymous(new FakeCustomToken()));
    }

    /**
     * @group legacy
     * @expectedDeprecation Configuring a custom remember me token class is deprecated since Symfony 4.2; have the "Symfony\Component\Security\Core\Tests\Authentication\FakeCustomToken" class extend the "Symfony\Component\Security\Core\Authentication\Token\RememberMeToken" class instead, and remove the "Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver" constructor argument.
     */
    public function testIsRememberMeDeprecationWithCustomClasses()
    {
        $resolver = new AuthenticationTrustResolver(null, FakeCustomToken::class);

        $this->assertTrue($resolver->isRememberMe(new FakeCustomToken()));
    }

    /**
     * @group legacy
     * @expectedDeprecation Configuring a custom remember me token class is deprecated since Symfony 4.2; have the "Symfony\Component\Security\Core\Tests\Authentication\FakeCustomToken" class extend the "Symfony\Component\Security\Core\Authentication\Token\RememberMeToken" class instead, and remove the "Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver" constructor argument.
     */
    public function testIsFullFledgedDeprecationWithCustomClasses()
    {
        $resolver = new AuthenticationTrustResolver(FakeCustomToken::class, FakeCustomToken::class);

        $this->assertFalse($resolver->isFullFledged(new FakeCustomToken()));
    }

    public function testIsAnonymousWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous($this->getToken()));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
        $this->assertTrue($resolver->isAnonymous($this->getAnonymousToken()));
        $this->assertTrue($resolver->isAnonymous(new RealCustomAnonymousToken()));
    }

    public function testIsRememberMeWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe($this->getToken()));
        $this->assertFalse($resolver->isRememberMe($this->getAnonymousToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
        $this->assertTrue($resolver->isRememberMe(new RealCustomRememberMeToken()));
    }

    public function testisFullFledgedWithClassAsConstructorButStillExtending()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged(new RealCustomRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged($this->getToken()));
    }

    protected function getToken()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
    }

    protected function getAnonymousToken()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\AnonymousToken')->setConstructorArgs(['', ''])->getMock();
    }

    protected function getRememberMeToken()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken')->setMethods(['setPersistent'])->disableOriginalConstructor()->getMock();
    }

    protected function getResolver()
    {
        return new AuthenticationTrustResolver(
            'Symfony\\Component\\Security\\Core\\Authentication\\Token\\AnonymousToken',
            'Symfony\\Component\\Security\\Core\\Authentication\\Token\\RememberMeToken'
        );
    }
}

class FakeCustomToken implements TokenInterface
{
    public function __serialize(): array
    {
    }

    public function serialize()
    {
    }

    public function __unserialize(array $data): void
    {
    }

    public function unserialize($serialized)
    {
    }

    public function __toString()
    {
    }

    public function getRoles()
    {
    }

    public function getCredentials()
    {
    }

    public function getUser()
    {
    }

    public function setUser($user)
    {
    }

    public function getUsername()
    {
    }

    public function isAuthenticated()
    {
    }

    public function setAuthenticated($isAuthenticated)
    {
    }

    public function eraseCredentials()
    {
    }

    public function getAttributes()
    {
    }

    public function setAttributes(array $attributes)
    {
    }

    public function hasAttribute($name)
    {
    }

    public function getAttribute($name)
    {
    }

    public function setAttribute($name, $value)
    {
    }
}

class RealCustomAnonymousToken extends AnonymousToken
{
    public function __construct()
    {
    }
}

class RealCustomRememberMeToken extends RememberMeToken
{
    public function __construct()
    {
    }
}
