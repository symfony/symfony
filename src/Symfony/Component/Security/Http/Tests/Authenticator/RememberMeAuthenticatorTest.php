<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\RememberMe\AbstractRememberMeServices;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

class RememberMeAuthenticatorTest extends TestCase
{
    private $rememberMeServices;
    private $tokenStorage;
    private $authenticator;
    private $request;

    protected function setUp(): void
    {
        $this->rememberMeServices = $this->createMock(RememberMeServicesInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorage::class);
        $this->authenticator = new RememberMeAuthenticator($this->rememberMeServices, 's3cr3t', $this->tokenStorage, [
            'name' => '_remember_me_cookie',
        ]);
        $this->request = new Request();
        $this->request->cookies->set('_remember_me_cookie', $val = $this->generateCookieValue());
        $this->request->attributes->set(AbstractRememberMeServices::COOKIE_ATTR_NAME, new Cookie('_remember_me_cookie', $val));
    }

    public function testSupportsTokenStorageWithToken()
    {
        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn(TokenInterface::class);

        $this->assertFalse($this->authenticator->supports($this->request));
    }

    public function testSupportsRequestWithoutAttribute()
    {
        $this->request->attributes->remove(AbstractRememberMeServices::COOKIE_ATTR_NAME);

        $this->assertNull($this->authenticator->supports($this->request));
    }

    public function testSupportsRequestWithoutCookie()
    {
        $this->request->cookies->remove('_remember_me_cookie');

        $this->assertFalse($this->authenticator->supports($this->request));
    }

    public function testSupports()
    {
        $this->assertNull($this->authenticator->supports($this->request));
    }

    public function testAuthenticate()
    {
        $this->rememberMeServices->expects($this->once())
            ->method('autoLogin')
            ->with($this->request)
            ->willReturn(new RememberMeToken($user = new User('wouter', 'test'), 'main', 'secret'));

        $passport = $this->authenticator->authenticate($this->request);

        $this->assertSame($user, $passport->getUser());
    }

    private function generateCookieValue()
    {
        return base64_encode(implode(AbstractRememberMeServices::COOKIE_DELIMITER, ['part1', 'part2']));
    }
}
