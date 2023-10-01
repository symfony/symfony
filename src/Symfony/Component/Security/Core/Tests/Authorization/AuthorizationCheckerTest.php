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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthorizationCheckerTest extends TestCase
{
    use ExpectDeprecationTrait;

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
        $accessDecisionManager = $this
            ->getMockBuilder(AccessDecisionManagerInterface::class)
            ->onlyMethods(['decide'])
            ->addMethods(['getDecision'])
            ->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->isInstanceOf(NullToken::class), ['ROLE_FOO'])
            ->willReturn(AccessDecision::createGranted());

        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager
        );

        $authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @group legacy
     */
    public function testVoteWithoutAuthenticationTokenLegacy()
    {
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $this->accessDecisionManager);

        $this->accessDecisionManager->expects($this->once())->method('decide')->with($this->isInstanceOf(NullToken::class))->willReturn(false);

        $this->expectDeprecation('Since symfony/security-core 6.3: Not implementing "%s::getDecision()" method is deprecated, and would be required in 7.0.');
        $authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @group legacy
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = $this
            ->getMockBuilder(AccessDecisionManagerInterface::class)
            ->onlyMethods(['decide'])
            ->addMethods(['getDecision'])
            ->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->identicalTo($token), $this->identicalTo(['ROLE_FOO']))
            ->willReturn($decide ? AccessDecision::createGranted() : AccessDecision::createDenied());

        $this->tokenStorage->setToken($token);
        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager
        );

        $this->assertSame($decide, $authorizationChecker->isGranted('ROLE_FOO'));
    }

    /**
     * @group legacy
     * @dataProvider isGrantedProvider
     */
    public function testIsGrantedLegacy($decide)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);

        $this->expectDeprecation('Since symfony/security-core 6.3: Not implementing "%s::getDecision()" method is deprecated, and would be required in 7.0.');
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

        $accessDecisionManager = $this
            ->getMockBuilder(AccessDecisionManagerInterface::class)
            ->onlyMethods(['decide'])
            ->addMethods(['getDecision'])
            ->getMock();
        $accessDecisionManager
            ->expects($this->once())
            ->method('getDecision')
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn(AccessDecision::createGranted());

        $this->tokenStorage->setToken($token);
        $authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $accessDecisionManager
        );

        $this->assertTrue($authorizationChecker->isGranted($attribute));
    }

    /**
     * @group legacy
     */
    public function testIsGrantedWithObjectAttributeLegacy()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn(true);
        $this->tokenStorage->setToken($token);

        $this->expectDeprecation('Since symfony/security-core 6.3: Not implementing "%s::getDecision()" method is deprecated, and would be required in 7.0.');
        $this->assertTrue($this->authorizationChecker->isGranted($attribute));
    }
}
