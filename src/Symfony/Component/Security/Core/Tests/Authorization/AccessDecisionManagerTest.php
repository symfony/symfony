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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testSetUnsupportedStrategy()
    {
        $this->expectException(\InvalidArgumentException::class);
        new AccessDecisionManager([$this->getVoter(VoterInterface::ACCESS_GRANTED)], 'fooBar');
    }

    /**
     * @group legacy
     *
     * @dataProvider getStrategyTests
     */
    public function testStrategies($strategy, $voters, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions, $expected)
    {
        $token = $this->createMock(TokenInterface::class);

        $this->expectDeprecation('Since symfony/security-core 5.4: Passing the access decision strategy as a string is deprecated, pass an instance of "Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface" instead.');
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->assertSame($expected, $manager->decide($token, ['ROLE_FOO']));
    }

    /**
     * @dataProvider provideBadVoterResults
     *
     * @group legacy
     */
    public function testDeprecatedVoter()
    {
        $token = $this->createMock(TokenInterface::class);
        $strategy = new class() implements AccessDecisionStrategyInterface {
            public function decide(\Traversable $results): bool
            {
                iterator_to_array($results);

                return true;
            }
        };

        $manager = new AccessDecisionManager([$this->getVoter(3)], $strategy);

        $this->expectDeprecation('Since symfony/security-core 5.3: Returning "%s" in "%s::vote()" is deprecated, return one of "Symfony\Component\Security\Core\Authorization\Voter\VoterInterface" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".');

        $manager->decide($token, ['ROLE_FOO']);
    }

    public static function provideBadVoterResults(): array
    {
        return [
            [3],
            [true],
        ];
    }

    public function testVoterCalls()
    {
        $token = $this->createMock(TokenInterface::class);

        $voters = [
            $this->getExpectedVoter(VoterInterface::ACCESS_DENIED),
            $this->getExpectedVoter(VoterInterface::ACCESS_GRANTED),
            $this->getUnexpectedVoter(),
        ];

        $strategy = new class() implements AccessDecisionStrategyInterface {
            public function decide(\Traversable $results): bool
            {
                $i = 0;
                foreach ($results as $result) {
                    switch ($i++) {
                        case 0:
                            Assert::assertSame(VoterInterface::ACCESS_DENIED, $result);

                            break;
                        case 1:
                            Assert::assertSame(VoterInterface::ACCESS_GRANTED, $result);

                            return true;
                    }
                }

                return false;
            }
        };

        $manager = new AccessDecisionManager($voters, $strategy);

        $this->assertTrue($manager->decide($token, ['ROLE_FOO']));
    }

    public static function getStrategyTests(): array
    {
        return [
            // affirmative
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(1, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 1, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 0, 1), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 0, 1), true, true, true],

            // consensus
            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(1, 2, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 1, 0), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(0, 0, 1), false, true, false],

            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(0, 0, 1), true, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 1), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 0), false, false, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 1), false, false, false],

            // unanimous
            [AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 0, 1), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 1, 0), false, true, false],

            [AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(0, 0, 2), false, true, false],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(0, 0, 2), true, true, true],

            // priority
            [AccessDecisionManager::STRATEGY_PRIORITY, [
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
                self::getVoter(VoterInterface::ACCESS_GRANTED),
                self::getVoter(VoterInterface::ACCESS_DENIED),
                self::getVoter(VoterInterface::ACCESS_DENIED),
            ], true, true, true],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
                self::getVoter(VoterInterface::ACCESS_DENIED),
                self::getVoter(VoterInterface::ACCESS_GRANTED),
                self::getVoter(VoterInterface::ACCESS_GRANTED),
            ], true, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], false, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
                self::getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], true, true, true],
        ];
    }

    public function testCacheableVoters()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);

        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersIgnoresNonStringAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->never())
            ->method('supportsAttribute');
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', [1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, [1337], 'bar'));
    }

    public function testCacheableVotersWithMultipleAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->exactly(2))
            ->method('supportsAttribute')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    [['foo'], false],
                    [['bar'], true],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            });
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 'bar'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo', 'bar'], 'bar', true));
    }

    public function testCacheableVotersWithEmptyAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->never())
            ->method('supportsAttribute');
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', [])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, [], 'bar'));
    }

    public function testCacheableVotersSupportsMethodsCalledOnce()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->exactly(2))
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersNotCalled()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            ->expects($this->never())
            ->method('supportsType');
        $voter
            ->expects($this->never())
            ->method('vote');

        $manager = new AccessDecisionManager([$voter]);
        $this->assertFalse($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersWithMultipleAttributesAndNonString()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            // Voter does not support "foo", but given 1337 is not a string, it implicitly supports it.
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo', 1337], 'bar', true));
    }

    protected static function getVoters($grants, $denies, $abstains): array
    {
        $voters = [];
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected static function getVoter($vote)
    {
        return new class($vote) implements VoterInterface {
            private $vote;

            public function __construct(int $vote)
            {
                $this->vote = $vote;
            }

            public function vote(TokenInterface $token, $subject, array $attributes)
            {
                return $this->vote;
            }
        };
    }

    private function getExpectedVoter(int $vote): VoterInterface
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->willReturn($vote);

        return $voter;
    }

    private function getUnexpectedVoter(): VoterInterface
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->never())->method('vote');

        return $voter;
    }
}
