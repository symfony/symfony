<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class ConsensusStrategyTest extends StrategyTestCase
{
    /**
     * @dataProvider provideScenarios
     */
    public function testDecide(AccessRequest $accessRequest, DecisionVote $expectedDecision, ?string $reason): void
    {
        // Arrange
        $accessControlManger = $this->getAccessControlManager();

        // Act
        $decision = $accessControlManger->decide($accessRequest, 'consensus');

        // Assert
        $this->assertEquals($expectedDecision, $decision->decision);
        $this->assertEquals($reason, $decision->reason);
    }

    /**
     * @return iterable{0: string, 1: AccessRequest, 2: DecisionVote, 3: string}
     */
    public function provideScenarios(): iterable
    {
        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_SUPER_ADMIN']));
        yield 'consensus strategy and deny on authenticated user' => [
            new AccessRequest($userToken,'ROLE_ALLOWED_TO_SWITCH'),
            DecisionVote::ACCESS_DENIED,
            'There is a tie.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_SUPER_ADMIN']));
        yield 'consensus strategy and grant on authenticated user' => [
            new AccessRequest($userToken,'ROLE_ALLOWED_TO_SWITCH', allowIfAllAbstainOrTie: true),
            DecisionVote::ACCESS_GRANTED,
            'There is a tie.',
        ];
    }
}
