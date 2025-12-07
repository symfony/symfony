<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class AffirmativeStrategyTest extends StrategyTestCase
{
    /**
     * @dataProvider provideScenarios
     */
    public function testDecide(AccessRequest $accessRequest, DecisionVote $expectedDecision, ?string $reason): void
    {
        // Arrange
        $accessControlManger = $this->getAccessControlManager();

        // Act
        $decision = $accessControlManger->decide($accessRequest, 'affirmative');

        // Assert
        $this->assertEquals($expectedDecision, $decision->decision);
        $this->assertEquals($reason, $decision->reason);
    }

    /**
     * @return iterable{0: string, 1: AccessRequest, 2: DecisionVote, 3: string}
     */
    public function provideScenarios(): iterable
    {
        yield 'affirmative strategy and deny on abstain' => [
            new AccessRequest(new NullToken(),'read', 'article'),
            DecisionVote::ACCESS_DENIED,
            'All voters abstained from voting.',
        ];

        yield 'affirmative strategy and grant on abstain' => [
            new AccessRequest(new NullToken(),'read', 'article', allowIfAllAbstainOrTie: true),
            DecisionVote::ACCESS_GRANTED,
            'All voters abstained from voting.',
        ];
        yield 'affirmative strategy and deny on unauthenticated user' => [
            new AccessRequest(new NullToken(),'ROLE_USER'),
            DecisionVote::ACCESS_DENIED,
            'At least one voter denied access.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUser);
        yield 'affirmative strategy and grant on authenticated user (classic interface)' => [
            new AccessRequest($userToken,'ROLE_USER'),
            DecisionVote::ACCESS_GRANTED,
            'The user has the required role.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole);
        yield 'affirmative strategy and grant on authenticated user (new interface)' => [
            new AccessRequest($userToken,'ROLE_USER'),
            DecisionVote::ACCESS_GRANTED,
            'The user has the required role.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_SUPER_ADMIN']));
        yield 'affirmative strategy and grant on authenticated user (inherited role)' => [
            new AccessRequest($userToken,'ROLE_ALLOWED_TO_SWITCH'),
            DecisionVote::ACCESS_GRANTED,
            'The user has the required role.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_ADMIN']));
        yield 'affirmative strategy and deny on authenticated user (inherited role)' => [
            new AccessRequest($userToken,'ROLE_ALLOWED_TO_SWITCH'),
            DecisionVote::ACCESS_DENIED,
            'At least one voter denied access.',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_ADMIN']));
        $expression = new Expression('"ROLE_ADMIN" in role_names and is_authenticated()');
        yield 'affirmative strategy and grant on expression' => [
            new AccessRequest($userToken,$expression),
            DecisionVote::ACCESS_GRANTED,
            'Access granted by expression',
        ];

        $userToken = $this->createMock(TokenInterface::class);
        $userToken->method('getUser')->willReturn(new FakeUserWithRole(roles: ['ROLE_ADMIN']));
        $expression = new Expression('"ROLE_SUPER_ADMIN" in role_names and is_fully_authenticated()');
        yield 'affirmative strategy and denied on expression' => [
            new AccessRequest($userToken,$expression),
            DecisionVote::ACCESS_DENIED,
            'At least one voter denied access.',
        ];
    }
}
