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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthorizationCheckerTest extends TestCase
{
    private MockObject&AccessDecisionManagerInterface $accessDecisionManager;
    private AuthorizationChecker $authorizationChecker;
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $this->tokenStorage = new TokenStorage();

        $this->authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager);
    }

    public function testVoteWithoutAuthenticationToken()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager);

        $this->accessDecisionManager->expects($this->once())->method('decide')->with($this->isInstanceOf(NullToken::class))->willReturn(false);

        $authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);
        $this->assertSame($decide, $this->authorizationChecker->isGranted('ROLE_FOO'));
    }

    public static function isGrantedProvider()
    {
        return [[true], [false]];
    }

    public function testIsGrantedWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn(true);
        $this->tokenStorage->setToken($token);
        $this->assertTrue($this->authorizationChecker->isGranted($attribute));
    }

    /**
     * @dataProvider isGrantedForUserProvider
     */
    public function testIsGrantedForUser(bool $decide, array $roles)
    {
        $user = new InMemoryUser('username', 'password', $roles);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->callback(static fn (OfflineTokenInterface $token) => $token->getUser() === $user), ['ROLE_FOO'])
            ->willReturn($decide);

        $this->assertSame($decide, $this->authorizationChecker->isGrantedForUser($user, 'ROLE_FOO'));
    }

    public static function isGrantedForUserProvider(): array
    {
        return [
            [false, ['ROLE_USER']],
            [true, ['ROLE_USER', 'ROLE_FOO']],
        ];
    }

    public function testIsGrantedForUserWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $user = new InMemoryUser('username', 'password', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->isInstanceOf(OfflineTokenInterface::class), [$attribute])
            ->willReturn(true);
        $this->assertTrue($this->authorizationChecker->isGrantedForUser($user, $attribute));
    }
}
