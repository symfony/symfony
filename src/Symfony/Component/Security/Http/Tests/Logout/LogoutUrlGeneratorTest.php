<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class LogoutUrlGeneratorTest extends TestCase
{
    /** @var TokenStorage */
    private $tokenStorage;
    /** @var LogoutUrlGenerator */
    private $generator;

    protected function setUp(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $this->tokenStorage = new TokenStorage();
        $this->generator = new LogoutUrlGenerator($requestStack, null, $this->tokenStorage);
    }

    public function testGetLogoutPath()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath('secured_area'));
    }

    public function testGetLogoutPathWithoutLogoutListenerRegisteredForKeyThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No LogoutListener found for firewall key "unregistered_key".');
        $this->generator->registerListener('secured_area', '/logout', null, null, null);

        $this->generator->getLogoutPath('unregistered_key');
    }

    public function testGuessFromToken()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('user', 'password'), 'secured_area'));
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame('/logout', $this->generator->getLogoutPath());
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

    public function testGuessFromTokenWithoutFirewallNameFallbacksToCurrentFirewall()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('username', 'password'), 'provider'));
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('secured_area');

        $this->assertSame('/logout', $this->generator->getLogoutPath());
    }

    public function testUnableToGuessWithoutCurrentFirewallThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This request is not behind a firewall, pass the firewall name manually to generate a logout URL.');
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->generator->getLogoutPath();
    }

    public function testUnableToGuessWithCurrentFirewallThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to find logout in the current firewall, pass the firewall name manually to generate a logout URL.');
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('admin');

        $this->generator->getLogoutPath();
    }
}
