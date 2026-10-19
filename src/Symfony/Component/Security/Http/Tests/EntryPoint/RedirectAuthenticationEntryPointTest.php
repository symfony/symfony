<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\RedirectAuthenticationEntryPoint;
use Symfony\Component\Security\Http\HttpUtils;

class RedirectAuthenticationEntryPointTest extends TestCase
{
    public function testStartRedirectsToThePath()
    {
        $entryPoint = new RedirectAuthenticationEntryPoint(new HttpUtils(), '/login');

        $response = $entryPoint->start(Request::create('https://example.com/admin'), new AuthenticationException());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://example.com/login', $response->getTargetUrl());
    }

    public function testStartRedirectsToTheAbsoluteUrl()
    {
        $entryPoint = new RedirectAuthenticationEntryPoint(new HttpUtils(null, null, '{^https?://%s$}i'), 'https://sso.example.com/login');

        $response = $entryPoint->start(Request::create('https://example.com/admin'));

        $this->assertSame('https://sso.example.com/login', $response->getTargetUrl());
    }

    public function testStartRedirectsToTheRoute()
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/login');

        $entryPoint = new RedirectAuthenticationEntryPoint(new HttpUtils($urlGenerator), 'app_login');

        $response = $entryPoint->start(Request::create('https://example.com/admin'));

        $this->assertSame('https://example.com/login', $response->getTargetUrl());
    }
}
