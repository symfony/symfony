<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Impersonate;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;

class ImpersonateUrlGeneratorTest extends TestCase
{
    private const CSRF_CONFIG = [
        'parameter' => '_switch_user',
        'enable_csrf' => true,
        'csrf_parameter' => '_token',
        'csrf_token_id' => 'impersonate',
    ];

    public function testUrlsAreBuiltFromTheCurrentUriWhenNoPathIsConfigured()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user']);

        $this->assertSame('/profile?page=2&_switch_user=kuba', $generator->generateImpersonationPath('kuba'));
        $this->assertSame('http://localhost/profile?page=2&_switch_user=kuba', $generator->generateImpersonationUrl('kuba'));
    }

    public function testUrlsAreBuiltFromTheGivenTargetWhenNoPathIsConfigured()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user']);

        $this->assertSame('/dashboard?_switch_user=kuba', $generator->generateImpersonationPath('kuba', '/dashboard'));
        $this->assertSame('http://localhost/dashboard?_switch_user=kuba', $generator->generateImpersonationUrl('kuba', '/dashboard'));
    }

    public function testExitUrlsAreBuiltFromTheGivenTargetWhenNoPathIsConfigured()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user'], true);

        $this->assertSame('/dashboard?_switch_user=_exit', $generator->generateExitPath('/dashboard'));
        $this->assertSame('http://localhost/dashboard?_switch_user=_exit', $generator->generateExitUrl('/dashboard'));
        $this->assertSame('/profile?page=2&_switch_user=_exit', $generator->generateExitPath());
    }

    public function testUrlsAreBuiltFromTheConfiguredPath()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame('/switch-user?_switch_user=kuba&_target_path=%2Fprofile%3Fpage%3D2', $generator->generateImpersonationPath('kuba'));
        $this->assertSame('http://localhost/switch-user?_switch_user=kuba&_target_path=%2Fprofile%3Fpage%3D2', $generator->generateImpersonationUrl('kuba'));
    }

    public function testUrlsAreBuiltFromTheGivenTargetWhenAPathIsConfigured()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame('/switch-user?_switch_user=kuba&_target_path=%2Fdashboard', $generator->generateImpersonationPath('kuba', '/dashboard'));
        $this->assertSame('http://localhost/switch-user?_switch_user=kuba&_target_path=%2Fdashboard', $generator->generateImpersonationUrl('kuba', '/dashboard'));
    }

    public function testUrlsAreBuiltFromTheConfiguredRoute()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => 'app_switch_user']);

        $this->assertSame('/switch-user?_switch_user=kuba&_target_path=%2Fprofile%3Fpage%3D2', $generator->generateImpersonationPath('kuba'));
        $this->assertSame('http://localhost/switch-user?_switch_user=kuba&_target_path=%2Fprofile%3Fpage%3D2', $generator->generateImpersonationUrl('kuba'));
    }

    public function testExitUrlsAreBuiltFromTheConfiguredPath()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user'], true);

        $this->assertSame('/switch-user?_switch_user=_exit&_target_path=%2Fprofile%3Fpage%3D2', $generator->generateExitPath());
        $this->assertSame('/switch-user?_switch_user=_exit&_target_path=%2Fdashboard', $generator->generateExitPath('/dashboard'));
        $this->assertSame('http://localhost/switch-user?_switch_user=_exit&_target_path=%2Fdashboard', $generator->generateExitUrl('/dashboard'));
    }

    public function testCsrfTokenIsAppendedWhenTheFirewallEnablesIt()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->atLeastOnce())
            ->method('getToken')->with('impersonate')
            ->willReturn(new CsrfToken('impersonate', 'T0K3N'));

        $generator = $this->createGenerator(self::CSRF_CONFIG, true, $csrfTokenManager);

        $this->assertSame('/profile?page=2&_switch_user=kuba&_token=T0K3N', $generator->generateImpersonationPath('kuba'));
        $this->assertSame('/profile?page=2&_switch_user=_exit&_token=T0K3N', $generator->generateExitPath());
    }

    public function testCsrfTokenIsNotAppendedWhenTheFirewallDoesNotEnableIt()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('getToken');

        $generator = $this->createGenerator(['parameter' => '_switch_user'], false, $csrfTokenManager);

        $this->assertSame('/profile?page=2&_switch_user=kuba', $generator->generateImpersonationPath('kuba'));
    }

    public function testAnExceptionIsThrownWithoutACsrfTokenManager()
    {
        $generator = $this->createGenerator(self::CSRF_CONFIG);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to generate the impersonate URLs without a CSRF token manager.');

        $generator->generateImpersonationPath('kuba');
    }

    public function testExitUrlsAreEmptyWhenNotImpersonating()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame('', $generator->generateExitPath());
        $this->assertSame('', $generator->generateExitUrl());
    }

    public function testFormPartsAreBuiltFromTheCurrentUriWhenNoPathIsConfigured()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user']);

        $this->assertSame([
            'action' => '/profile?page=2',
            'fields' => ['_switch_user' => 'kuba'],
        ], $generator->generateImpersonationForm('kuba'));
    }

    public function testFormPartsAreBuiltFromTheConfiguredPath()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame([
            'action' => '/switch-user',
            'fields' => ['_switch_user' => 'kuba', '_target_path' => '/profile?page=2'],
        ], $generator->generateImpersonationForm('kuba'));
    }

    public function testFormPartsAreBuiltFromTheConfiguredRoute()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => 'app_switch_user']);

        $this->assertSame([
            'action' => '/switch-user',
            'fields' => ['_switch_user' => 'kuba', '_target_path' => '/profile?page=2'],
        ], $generator->generateImpersonationForm('kuba'));
    }

    public function testFormFieldsCarryATokenOfTheCsrfTokenManagerOfTheFirewall()
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('getToken')->with('impersonate')
            ->willReturn(new CsrfToken('impersonate', 'T0K3N'));

        $generator = $this->createGenerator(self::CSRF_CONFIG + ['path' => '/switch-user'], false, $csrfTokenManager);

        $this->assertSame([
            'action' => '/switch-user',
            'fields' => ['_switch_user' => 'kuba', '_token' => 'T0K3N', '_target_path' => '/profile?page=2'],
        ], $generator->generateImpersonationForm('kuba'));
    }

    public function testFormPartsTargetTheGivenUri()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame([
            'action' => '/switch-user',
            'fields' => ['_switch_user' => 'kuba', '_target_path' => '/dashboard'],
        ], $generator->generateImpersonationForm('kuba', '/dashboard'));
    }

    public function testExitFormPartsTargetTheGivenUri()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user'], true);

        $this->assertSame([
            'action' => '/switch-user',
            'fields' => ['_switch_user' => '_exit', '_target_path' => '/dashboard'],
        ], $generator->generateExitForm('/dashboard'));
    }

    public function testExitFormPartsAreEmptyWhenNotImpersonating()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => '/switch-user']);

        $this->assertSame(['action' => '', 'fields' => []], $generator->generateExitForm());
    }

    public function testParametersTheRouteTakesAsPlaceholdersStayOutOfTheFormFields()
    {
        $generator = $this->createGenerator(['parameter' => '_switch_user', 'path' => 'app_switch_user_restful']);

        $this->assertSame([
            'action' => '/switch-user/kuba',
            'fields' => ['_target_path' => '/profile?page=2'],
        ], $generator->generateImpersonationForm('kuba'));
        $this->assertSame('/switch-user/kuba?_target_path=%2Fprofile%3Fpage%3D2', $generator->generateImpersonationPath('kuba'));
    }

    private function createGenerator(array $switchUser, bool $impersonating = false, ?CsrfTokenManagerInterface $csrfTokenManager = null): ImpersonateUrlGenerator
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/profile?page=2'));

        $firewallMap = $this->createStub(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(new FirewallConfig('main', 'security.user_checker', switchUser: $switchUser));

        $originalToken = new UsernamePasswordToken(new InMemoryUser('admin', ''), 'main');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($impersonating ? new SwitchUserToken(new InMemoryUser('kuba', ''), 'main', [], $originalToken) : $originalToken);

        $routes = new RouteCollection();
        $routes->add('app_switch_user', new Route('/switch-user'));
        $routes->add('app_switch_user_restful', new Route('/switch-user/{_switch_user}'));

        return new ImpersonateUrlGenerator($requestStack, $firewallMap, $tokenStorage, new UrlGenerator($routes, new RequestContext()), $csrfTokenManager);
    }
}
