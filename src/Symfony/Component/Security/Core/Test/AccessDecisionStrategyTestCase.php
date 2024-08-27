<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\LogicException;

/**
 * Abstract test case for access decision strategies.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
abstract class AccessDecisionStrategyTestCase extends TestCase
{
    /**
     * @dataProvider provideStrategyTests
     *
     * @param VoterInterface[] $voters
     */
    #[DataProvider('provideStrategyTests')]
    final public function testDecide(AccessDecisionStrategyInterface $strategy, array $voters, AccessDecision $expected)
    {
        $token = $this->createMock(TokenInterface::class);
        $manager = new AccessDecisionManager($voters, $strategy);

        $this->assertSame($expected->isGranted(), $manager->decide($token, ['ROLE_FOO']));
    }

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

    /**
     * @return iterable<array{AccessDecisionStrategyInterface, VoterInterface[], bool}>
     */
    abstract public static function provideStrategyTests(): iterable;

    /**
     * @return VoterInterface[]
     */
    final protected static function getVoters(int $grants, int $denies, int $abstains): array
    {
        $voters = [];
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = static::getVoter(VoterInterface::ACCESS_GRANTED);
            $voters[] = static::getVoterWithVoteObject(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = static::getVoter(VoterInterface::ACCESS_DENIED);
            $voters[] = static::getVoterWithVoteObject(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = static::getVoter(VoterInterface::ACCESS_ABSTAIN);
            $voters[] = static::getVoterWithVoteObject(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
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

            public function __call($function, $args)
            {
                throw new LogicException('This function must not be acceded.');
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

    final protected static function getAccessDecision(bool $decision, array $votes): AccessDecision
    {
        return new AccessDecision($decision ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
            array_map(fn ($v) => new Vote($v), $votes));
    }
}
