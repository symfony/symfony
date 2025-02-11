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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
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

    public function testIsGrantedWithAccessDecisionObject()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = new class implements AccessDecisionManagerInterface {
            public function decide(TokenInterface $token, array $attributes, mixed $object = null, bool $allowMultipleAttributes = false, ?AccessDecision &$accessDecision = null): bool
            {
                $accessDecision = new AccessDecision(true);

                return $accessDecision->getAccess();
            }
        };

        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);

        $this->tokenStorage->setToken($token);

        $accessDecision = null;
        $decision = $authorizationChecker->isGranted($attribute, $token, $accessDecision);
        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertTrue($decision);
        $this->assertTrue($accessDecision->getAccess());
        $this->assertEmpty($accessDecision->getMessage());
    }

    public function testIsGrantedWithoutAccessDecisionObject()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = new class implements AccessDecisionManagerInterface {
            public function decide(TokenInterface $token, array $attributes, mixed $object = null): bool
            {
                return true;
            }
        };

        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);

        $this->tokenStorage->setToken($token);

        $accessDecision = null;
        $decision = $authorizationChecker->isGranted($attribute, $token, $accessDecision);
        $this->assertNull($accessDecision);
        $this->assertTrue($decision);
    }

    public function testIsGrantedWithAccessDecisionObjectFromADM()
    {
        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager = new class implements AccessDecisionManagerInterface {
            public function decide(TokenInterface $token, array $attributes, mixed $object = null, bool $allowMultipleAttributes = false, ?AccessDecision &$accessDecision = null): bool
            {
                $accessDecision = new AccessDecision(true, [], 'from accessDecisionManager');

                return $accessDecision->getAccess();
            }
        };

        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);
        $this->tokenStorage->setToken($token);

        $accessDecision = null;
        $decision = $authorizationChecker->isGranted($attribute, $token, $accessDecision);
        $this->assertTrue($decision);
        $this->assertTrue($accessDecision->getAccess());
        $this->assertSame('from accessDecisionManager', $accessDecision->getMessage());
    }
}
