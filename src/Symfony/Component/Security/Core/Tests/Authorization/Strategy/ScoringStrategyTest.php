<?php

namespace Authorization\Strategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\ScoringStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ScoringStrategyTest extends TestCase
{
    /**
     * @dataProvider provideStrategyTests
     *
     * @param VoterInterface[] $voters
     */
    final public function testGetDecision(AccessDecisionStrategyInterface $strategy, array $voters, AccessDecision $expected)
    {
        $token = $this->createMock(TokenInterface::class);
        $manager = new AccessDecisionManager($voters, $strategy);

        $this->assertEquals($expected, $manager->getDecision($token, ['ROLE_FOO']));
    }

    public static function provideStrategyTests(): iterable
    {
        $strategy = new ScoringStrategy();

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_ABSTAIN),
        ], self::getAccessDecision(false, [
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_ABSTAIN, false],
        ], 'score = -1'
        )];

        yield [$strategy, [
            self::getVoterWithVoteObject(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_ABSTAIN),
        ], self::getAccessDecision(false, [
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_ABSTAIN, false],
        ], 'score = -1'
        )];

        yield [$strategy, [
            self::getVoterWithVoteObjectAndScoring(5),
        ], self::getAccessDecision(true, [
            [5, true],
        ], 'score = 5'
        )];

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoterWithVoteObjectAndScoring(VoterInterface::ACCESS_ABSTAIN),
            self::getVoterWithVoteObjectAndScoring(2),
        ], self::getAccessDecision(false, [
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_ABSTAIN, true],
            [2, true],
        ], 'score = -1'
        )];

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoterWithVoteObjectAndScoring(5),
        ], self::getAccessDecision(true, [
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_DENIED, false],
            [VoterInterface::ACCESS_DENIED, false],
            [5, true],
        ], 'score = 1'
        )];
    }


    final protected static function getVoter(int $vote): VoterInterface
    {
        return new class($vote) implements VoterInterface {
            public function __construct(
                private int $vote,
            ) {
            }

            public function vote(TokenInterface $token, $subject, array $attributes): int
            {
                return $this->vote;
            }
        };
    }

    final protected static function getVoterWithVoteObject(int $vote): VoterInterface
    {
        return new class($vote) implements VoterInterface {
            public function __construct(
                private int $vote,
            ) {
            }

            public function vote(TokenInterface $token, $subject, array $attributes): int
            {
                return $this->vote;
            }

            public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
            {
                return new Vote($this->vote);
            }
        };
    }

    final protected static function getVoterWithVoteObjectAndScoring(int $vote): VoterInterface
    {
        return new class($vote) implements VoterInterface {
            public function __construct(
                private int $vote,
            ) {
            }

            public function vote(TokenInterface $token, $subject, array $attributes): int
            {
                return $this->vote;
            }

            public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
            {
                return new Vote($this->vote, scoring: true);
            }
        };
    }

    final protected static function getAccessDecision(bool $decision, array $votes, string $message): AccessDecision
    {
        return new AccessDecision($decision ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
            array_map(fn ($vote) => new Vote($vote[0], scoring: $vote[1]), $votes),
            $message
        );
    }
}
