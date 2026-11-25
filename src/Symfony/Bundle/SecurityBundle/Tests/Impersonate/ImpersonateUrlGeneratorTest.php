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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;

class ImpersonateUrlGeneratorTest extends TestCase
{
    public function testExitUrlsAreBuiltFromTheCurrentUri()
    {
        $generator = $this->createGenerator('/profile?page=2');

        $this->assertSame('/profile?page=2&_switch_user=_exit', $generator->generateExitPath());
        $this->assertSame('http://localhost/profile?page=2&_switch_user=_exit', $generator->generateExitUrl());
        $this->assertSame('/dashboard?_switch_user=_exit', $generator->generateExitPath('/dashboard'));
    }

    public function testExitUrlsEncodeTheLeadingDoubleSlashOfTheCurrentUri()
    {
        $generator = $this->createGenerator('//example.com/profile');

        $this->assertSame('/%2Fexample.com/profile?_switch_user=_exit', $generator->generateExitPath());
        $this->assertSame('http://localhost/%2Fexample.com/profile?_switch_user=_exit', $generator->generateExitUrl());
        $this->assertSame('//example.com/profile?_switch_user=_exit', $generator->generateExitPath('//example.com/profile'));
    }

    private function createGenerator(string $requestUri): ImpersonateUrlGenerator
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => $requestUri]));

        $firewallMap = $this->createStub(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(new FirewallConfig('main', 'security.user_checker', null, true, false, null, null, null, null, null, [], ['parameter' => '_switch_user']));

        $originalToken = new UsernamePasswordToken(new InMemoryUser('admin', ''), 'main');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('kuba', ''), 'main', [], $originalToken));

        return new ImpersonateUrlGenerator($requestStack, $firewallMap, $tokenStorage);
    }
}
