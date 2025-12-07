<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class UnanimousStrategyTest extends StrategyTestCase
{
    /**
     * @dataProvider provideScenarios
     */
    public function testDecide(AccessRequest $accessRequest, DecisionVote $expectedDecision, ?string $reason): void
    {
        // Arrange
        $accessControlManger = $this->getAccessControlManager();

        // Act
        $decision = $accessControlManger->decide($accessRequest, 'unanimous');

        // Assert
        $this->assertEquals($expectedDecision, $decision->decision);
        $this->assertEquals($reason, $decision->reason);
    }

    /**
     * @return iterable{0: string, 1: AccessRequest, 2: DecisionVote, 3: string}
     */
    public function provideScenarios(): iterable
    {
        // In this scenario, both RoleVoter and RoleHierarchyVoter will vote. The former will deny access.
        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_SUPER_ADMIN']));
        yield 'unanimous strategy and deny on authenticated user' => [
            new AccessRequest($userToken,'ROLE_ALLOWED_TO_SWITCH'),
            DecisionVote::ACCESS_DENIED,
            'The user does not have the required role.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_ADMIN']));
        yield 'unanimous strategy and grant on authenticated user' => [
            new AccessRequest($userToken,'ROLE_ADMIN'),
            DecisionVote::ACCESS_GRANTED,
            'All non-abstaining voters granted access.',
        ];
    }
}
