<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symphony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class LogoutUrlGeneratorTest extends TestCase
{
    /** @var TokenStorage */
    private $tokenStorage;
    /** @var LogoutUrlGenerator */
    private $generator;

    protected function setUp()
    {
        $requestStack = $this->getMockBuilder(RequestStack::class)->getMock();
        $request = $this->getMockBuilder(Request::class)->getMock();
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $this->tokenStorage = new TokenStorage();
        $this->generator = new LogoutUrlGenerator($requestStack, null, $this->tokenStorage);
    }

    public function testGetLogoutPath()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath('secured_area'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No LogoutListener found for firewall key "unregistered_key".
     */
    public function testGetLogoutPathWithoutLogoutListenerRegisteredForKeyThrowsException()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null, null);

        $this->generator->getLogoutPath('unregistered_key');
    }

    public function testGuessFromToken()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken('user', 'password', 'secured_area'));
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unable to generate a logout url for an anonymous token.
     */
    public function testGuessFromAnonymousTokenThrowsException()
    {
        $this->tokenStorage->setToken(new AnonymousToken('default', 'anon.'));

        $this->generator->getLogoutPath();
    }

    public function testGuessFromCurrentFirewallKey()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('secured_area');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testGuessFromCurrentFirewallContext()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null, null, 'secured');
        $this->generator->setCurrentFirewall('admin', 'secured');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testGuessFromTokenWithoutProviderKeyFallbacksToCurrentFirewall()
    {
        $this->tokenStorage->setToken($this->getMockBuilder(TokenInterface::class)->getMock());
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('secured_area');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unable to find the current firewall LogoutListener, please provide the provider key manually
     */
    public function testUnableToGuessThrowsException()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->generator->getLogoutPath();
    }
}
