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
use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * @group legacy
 */
class AnonymousAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider('foo');

        self::assertTrue($provider->supports($this->getSupportedToken('foo')));
        self::assertFalse($provider->supports(self::createMock(TokenInterface::class)));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider('foo');

        $provider->authenticate(self::createMock(TokenInterface::class));
    }

    public function testAuthenticateWhenSecretIsNotValid()
    {
        self::expectException(BadCredentialsException::class);
        $provider = $this->getProvider('foo');

        $provider->authenticate($this->getSupportedToken('bar'));
    }

    public function testAuthenticate()
    {
        $provider = $this->getProvider('foo');
        $token = $this->getSupportedToken('foo');

        self::assertSame($token, $provider->authenticate($token));
    }

    protected function getSupportedToken($secret)
    {
        $token = self::getMockBuilder(AnonymousToken::class)->setMethods(['getSecret'])->disableOriginalConstructor()->getMock();
        $token->expects(self::any())
              ->method('getSecret')
              ->willReturn($secret)
        ;

        return $token;
    }

    protected function getProvider($secret)
    {
        return new AnonymousAuthenticationProvider($secret);
    }
}
