<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Core\Exception\DisabledException;

class RememberMeAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        $this->assertTrue($provider->supports($this->getSupportedToken()));
        $this->assertFalse($provider->supports($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AuthenticationException');
        $this->expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $provider->authenticate($token);
    }

    public function testAuthenticateWhenSecretsDoNotMatch()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $provider = $this->getProvider(null, 'secret1');
        $token = $this->getSupportedToken(null, 'secret2');

        $provider->authenticate($token);
    }

    public function testAuthenticateWhenPreChecksFails()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\DisabledException');
        $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        $userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->willThrowException(new DisabledException());

        $provider = $this->getProvider($userChecker);

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $user->expects($this->exactly(2))
             ->method('getRoles')
             ->willReturn(['ROLE_FOO']);

        $provider = $this->getProvider();

        $token = $this->getSupportedToken($user);
        $authToken = $provider->authenticate($token);

        $this->assertInstanceOf('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken', $authToken);
        $this->assertSame($user, $authToken->getUser());
        $this->assertEquals(['ROLE_FOO'], $authToken->getRoleNames());
        $this->assertEquals('', $authToken->getCredentials());
    }

    protected function getSupportedToken($user = null, $secret = 'test')
    {
        if (null === $user) {
            $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
            $user
                ->expects($this->any())
                ->method('getRoles')
                ->willReturn([]);
        }

        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\RememberMeToken')->setMethods(['getProviderKey'])->setConstructorArgs([$user, 'foo', $secret])->getMock();
        $token
            ->expects($this->once())
            ->method('getProviderKey')
            ->willReturn('foo');

        return $token;
    }

    protected function getProvider($userChecker = null, $key = 'test')
    {
        if (null === $userChecker) {
            $userChecker = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserCheckerInterface')->getMock();
        }

        return new RememberMeAuthenticationProvider($userChecker, $key, 'foo');
    }
}
