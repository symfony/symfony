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
        self::expectException(\InvalidArgumentException::class);
        new AccessDecisionManager([$this->getVoter(VoterInterface::ACCESS_GRANTED)], 'fooBar');
    }

    /**
     * @group legacy
     *
     * @dataProvider getStrategyTests
     */
    public function testStrategies($strategy, $voters, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions, $expected)
    {
        $token = self::createMock(TokenInterface::class);

        $this->expectDeprecation('Since symfony/security-core 5.4: Passing the access decision strategy as a string is deprecated, pass an instance of "Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface" instead.');
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        self::assertSame($expected, $manager->decide($token, ['ROLE_FOO']));
    }

    /**
     * @dataProvider provideBadVoterResults
     *
     * @group legacy
     */
    public function testDeprecatedVoter()
    {
        $token = self::createMock(TokenInterface::class);
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

    public function provideBadVoterResults(): array
    {
        return [
            [3],
            [true],
        ];
    }

    public function testVoterCalls()
    {
        $token = self::createMock(TokenInterface::class);

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

        self::assertTrue($manager->decide($token, ['ROLE_FOO']));
    }

    public function getStrategyTests()
    {
        return [
            // affirmative
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 1, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), true, true, true],

            // consensus
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 2, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 1, 0), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), false, true, false],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), true, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, false, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, false, false],

            // unanimous
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 1), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 1, 0), false, true, false],

            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), false, true, false],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), true, true, true],

            // priority
            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
            ], true, true, true],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
            ], true, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], false, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], true, true, true],
        ];
    }

    public function testCacheableVoters()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersIgnoresNonStringAttributes()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::never())
            ->method('supportsAttribute');
        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'bar', [1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, [1337], 'bar'));
    }

    public function testCacheableVotersWithMultipleAttributes()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::exactly(2))
            ->method('supportsAttribute')
            ->withConsecutive(['foo'], ['bar'])
            ->willReturnOnConsecutiveCalls(false, true);
        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 'bar'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, ['foo', 'bar'], 'bar', true));
    }

    public function testCacheableVotersWithEmptyAttributes()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::never())
            ->method('supportsAttribute');
        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'bar', [])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, [], 'bar'));
    }

    public function testCacheableVotersSupportsMethodsCalledOnce()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::exactly(2))
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, ['foo'], 'bar'));
        self::assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersNotCalled()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            ->expects(self::never())
            ->method('supportsType');
        $voter
            ->expects(self::never())
            ->method('vote');

        $manager = new AccessDecisionManager([$voter]);
        self::assertFalse($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersWithMultipleAttributesAndNonString()
    {
        $token = self::createMock(TokenInterface::class);
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $voter
            ->expects(self::once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            // Voter does not support "foo", but given 1337 is not a string, it implicitly supports it.
            ->expects(self::once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        self::assertTrue($manager->decide($token, ['foo', 1337], 'bar', true));
    }

    protected function getVoters($grants, $denies, $abstains)
    {
        $voters = [];
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected function getVoter($vote)
    {
        $voter = self::createMock(VoterInterface::class);
        $voter->expects(self::any())
              ->method('vote')
              ->willReturn($vote);

        return $voter;
    }

    private function getExpectedVoter(int $vote): VoterInterface
    {
        $voter = self::createMock(VoterInterface::class);
        $voter->expects(self::once())
            ->method('vote')
            ->willReturn($vote);

        return $voter;
    }

    private function getUnexpectedVoter(): VoterInterface
    {
        $voter = self::createMock(VoterInterface::class);
        $voter->expects(self::never())->method('vote');

        return $voter;
    }
}
