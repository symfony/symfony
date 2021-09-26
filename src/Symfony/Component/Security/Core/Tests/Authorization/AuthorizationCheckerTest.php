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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthorizationCheckerTest extends TestCase
{
    use ExpectDeprecationTrait;

    private $authenticationManager;
    private $accessDecisionManager;
    private $authorizationChecker;
    private $tokenStorage;

    protected function setUp(): void
    {
        $this->accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
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
    public function testLegacyVoteAuthenticatesTokenIfNecessary()
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider');
        $this->tokenStorage->setToken($token);

        $newToken = new UsernamePasswordToken('username', 'password', 'provider');

        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);
        $this->authorizationChecker = new AuthorizationChecker($this->tokenStorage, $authenticationManager, $this->accessDecisionManager, false, false);
        $authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($token))
            ->willReturn($newToken);

        // default with() isn't a strict check
        $tokenComparison = function ($value) use ($newToken) {
            // make sure that the new token is used in "decide()" and not the old one
            return $value === $newToken;
        };

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->callback($tokenComparison))
            ->willReturn(true);

        // first run the token has not been re-authenticated yet, after isGranted is called, it should be equal
        $this->assertNotSame($newToken, $this->tokenStorage->getToken());
        $this->assertTrue($this->authorizationChecker->isGranted('foo'));
        $this->assertSame($newToken, $this->tokenStorage->getToken());
    }

    public function testVoteAuthenticatesTokenIfNecessary()
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider');
        $this->tokenStorage->setToken($token);

        $accessDecisionManager = $this->createMock(AccessDecisionManager::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->identicalTo($token), ['foo'])
            ->willReturn(AccessDecision::createGranted());

        $authorizationChecker = new AuthorizationChecker($this->tokenStorage,  $accessDecisionManager, false, false);

        // first run the token has not been re-authenticated yet, after isGranted is called, it should be equal
        $this->assertSame($token, $this->tokenStorage->getToken());
        $this->assertTrue($authorizationChecker->isGranted('foo'));
    }

    /**
     * @group legacy
     */
    public function testLegacyVoteWithoutAuthenticationToken()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager);

        $this->expectException(AuthenticationCredentialsNotFoundException::class);

        $authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @group legacy
     */
    public function testLegacyVoteWithoutAuthenticationToken2()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager, false, false);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->isInstanceOf(NullToken::class))
            ->willReturn(true);
        $this->expectDeprecation('Since symfony/security-core 5.4: Not implementing "%s::getDecision()" method is deprecated, and would be required in 6.0.');
        $this->assertTrue($authorizationChecker->isGranted('ANONYMOUS'));
    }

    public function testVoteWithoutAuthenticationToken()
    {
        $accessDecisionManager = $this->createMock(AccessDecisionManager::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->isInstanceOf(NullToken::class), ['ANONYMOUS'])
            ->willReturn(AccessDecision::createGranted());

        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager,
            false,
            false
        );
        $this->assertTrue($authorizationChecker->isGranted('ANONYMOUS'));
    }

    /**
     * @dataProvider isGrantedProvider
     * @group legacy
     */
    public function testLegacyIsGranted($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);
        $this->expectDeprecation('Since symfony/security-core 5.4: Not implementing "%s::getDecision()" method is deprecated, and would be required in 6.0.');
        $this->assertSame($decide, $this->authorizationChecker->isGranted('ROLE_FOO'));
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = $this->createMock(AccessDecisionManager::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->identicalTo($token), $this->identicalTo(['ROLE_FOO']))
            ->willReturn($decide ? AccessDecision::createGranted() : AccessDecision::createDenied());
        $this->tokenStorage->setToken($token);
        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager,
            false,
            false
        );

        $this->assertSame($decide, $authorizationChecker->isGranted('ROLE_FOO'));
    }

    public function isGrantedProvider()
    {
        return [[true], [false]];
    }

    /**
     * @group legacy
     */
    public function testLegacyIsGrantedWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn(true);
        $this->tokenStorage->setToken($token);
        $this->expectDeprecation('Since symfony/security-core 5.4: Not implementing "%s::getDecision()" method is deprecated, and would be required in 6.0.');
        $this->assertTrue($this->authorizationChecker->isGranted($attribute));
    }

    public function testIsGrantedWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = $this->createMock(AccessDecisionManager::class);
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn(AccessDecision::createGranted());
        $this->tokenStorage->setToken($token);
        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager,
            false,
            false
        );
        $this->assertTrue($authorizationChecker->isGranted($attribute));
    }
}
