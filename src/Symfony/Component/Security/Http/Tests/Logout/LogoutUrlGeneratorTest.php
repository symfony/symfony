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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class LogoutUrlGeneratorTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private LogoutUrlGenerator $generator;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

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
        $this->generator->registerListener('secured_area', '/logout', null, null, null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No LogoutListener found for firewall key "unregistered_key".');

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
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This request is not behind a firewall, pass the firewall name manually to generate a logout URL.');

        $this->generator->getLogoutPath();
    }

    public function testUnableToGuessWithCurrentFirewallThrowsException()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);
        $this->generator->setCurrentFirewall('admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to find logout in the current firewall, pass the firewall name manually to generate a logout URL.');

        $this->generator->getLogoutPath();
    }

    public function testStateLeakWhenCalledTwiceWithoutReset()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->generator->setCurrentFirewall('secured_area');
        $this->assertSame('/logout', $this->generator->getLogoutPath());

        $this->generator->reset();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This request is not behind a firewall, pass the firewall name manually to generate a logout URL.');
        $this->generator->getLogoutPath();
    }

    public function testGetLogoutFormWithoutCsrfProtection()
    {
        $this->generator->registerListener('secured_area', '/logout', null, null);

        $this->assertSame(['action' => '/logout', 'fields' => []], $this->generator->getLogoutForm('secured_area'));
    }

    public function testGetLogoutFormCarriesTheCsrfTokenUnderTheConfiguredParameter()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('getToken')->with('logout_id')
            ->willReturn(new CsrfToken('logout_id', 'T0K3N'));

        $this->generator->registerListener('secured_area', '/logout', 'logout_id', '_token', $csrfTokenManager);

        $this->assertSame([
            'action' => '/logout',
            'fields' => ['_token' => 'T0K3N'],
        ], $this->generator->getLogoutForm('secured_area'));
    }

    public function testGetLogoutFormFromARouteName()
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters) => '/logout'.($parameters ? '?'.http_build_query($parameters, '', '&') : '')
        );

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('logout', 'T0K3N'));

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $generator = new LogoutUrlGenerator($requestStack, $router, $this->tokenStorage);
        $generator->registerListener('secured_area', 'app_logout', 'logout', '_csrf_token', $csrfTokenManager);

        $this->assertSame([
            'action' => '/logout',
            'fields' => ['_csrf_token' => 'T0K3N'],
        ], $generator->getLogoutForm('secured_area'));
    }

    public function testTheLogoutFormAndTheLogoutPathCarryTheSameParameters()
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('logout', 'T0K3N'));

        $this->generator->registerListener('secured_area', '/logout', 'logout', '_csrf_token', $csrfTokenManager);

        ['action' => $action, 'fields' => $fields] = $this->generator->getLogoutForm('secured_area');

        $this->assertSame($this->generator->getLogoutPath('secured_area'), $action.'?'.http_build_query($fields, '', '&'));
    }
}
