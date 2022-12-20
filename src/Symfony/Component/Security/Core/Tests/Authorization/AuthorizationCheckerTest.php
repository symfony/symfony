<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthorizationCheckerTest extends TestCase
{
    private $authenticationManager;
    private $accessDecisionManager;
    private $authorizationChecker;
    private $tokenStorage;

    protected function setUp(): void
    {
        $this->accessDecisionManager = self::createMock(AccessDecisionManagerInterface::class);
        $this->tokenStorage = new TokenStorage();

        $this->authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $this->accessDecisionManager,
            false,
            false
        );
    }

    /**
     * @group legacy
     */
    public function testVoteAuthenticatesTokenIfNecessary()
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider');
        $this->tokenStorage->setToken($token);

        $newToken = new UsernamePasswordToken('username', 'password', 'provider');

        $authenticationManager = self::createMock(AuthenticationManagerInterface::class);
        $this->authorizationChecker = new AuthorizationChecker($this->tokenStorage, $authenticationManager, $this->accessDecisionManager, false, false);
        $authenticationManager
            ->expects(self::once())
            ->method('authenticate')
            ->with(self::equalTo($token))
            ->willReturn($newToken);

        // default with() isn't a strict check
        $tokenComparison = function ($value) use ($newToken) {
            // make sure that the new token is used in "decide()" and not the old one
            return $value === $newToken;
        };

        $this->accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::callback($tokenComparison))
            ->willReturn(true);

        // first run the token has not been re-authenticated yet, after isGranted is called, it should be equal
        self::assertNotSame($newToken, $this->tokenStorage->getToken());
        self::assertTrue($this->authorizationChecker->isGranted('foo'));
        self::assertSame($newToken, $this->tokenStorage->getToken());
    }

    /**
     * @group legacy
     */
    public function testLegacyVoteWithoutAuthenticationToken()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager);

        self::expectException(AuthenticationCredentialsNotFoundException::class);

        $authorizationChecker->isGranted('ROLE_FOO');
    }

    public function testVoteWithoutAuthenticationToken()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager, false, false);

        $this->accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::isInstanceOf(NullToken::class))
            ->willReturn(true);

        self::assertTrue($authorizationChecker->isGranted('ANONYMOUS'));
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);
        self::assertSame($decide, $this->authorizationChecker->isGranted('ROLE_FOO'));
    }

    public function isGrantedProvider()
    {
        return [[true], [false]];
    }

    public function testIsGrantedWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects(self::once())
            ->method('decide')
            ->with(self::identicalTo($token), self::identicalTo([$attribute]))
            ->willReturn(true);
        $this->tokenStorage->setToken($token);
        self::assertTrue($this->authorizationChecker->isGranted($attribute));
    }
}
