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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends TestCase
{
    public function provideBadVoterResults(): array
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
            $this->getExpectedVoter(Vote::createDenied()),
            $this->getExpectedVoter(Vote::createGranted()),
            $this->getUnexpectedVoter(),
        ];

        $strategy = new class() implements AccessDecisionStrategyInterface {
            public function getDecision(\Traversable $votes): AccessDecision
            {
                $i = 0;
                /** @var Vote $vote */
                foreach ($votes as $vote) {
                    switch ($i++) {
                        case 0:
                            Assert::assertSame(VoterInterface::ACCESS_DENIED, $vote->getAccess());

                            break;
                        case 1:
                            Assert::assertSame(VoterInterface::ACCESS_GRANTED, $vote->getAccess());

                            return AccessDecision::createGranted();
                    }
                }

                return AccessDecision::createDenied();
            }

            public function decide(\Traversable $results): bool
            {
                throw new \RuntimeException('Method should not be called');
            }
        };

        $manager = new AccessDecisionManager($voters, $strategy);

        $expectedDecision = AccessDecision::createGranted();
        $this->assertEquals($expectedDecision, $manager->getDecision($token, ['ROLE_FOO']));
    }

    /**
     * @group legacy
     */
    public function testVoterCallsLegacy()
    {
        $token = $this->createMock(TokenInterface::class);

        $voters = [
            $this->getExpectedVoterLegacy(VoterInterface::ACCESS_DENIED),
            $this->getExpectedVoterLegacy(VoterInterface::ACCESS_GRANTED),
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

    public function testCacheableVoters()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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

        $vote = Vote::createGranted();
        $voter
            ->expects($this->once())
            ->method('getVote')
            ->with($token, 'bar', ['foo'])
            ->willReturn($vote);

        $manager = new AccessDecisionManager([$voter]);

        $expectedDecision = AccessDecision::createGranted([$vote]);
        $this->assertEquals($expectedDecision, $manager->getDecision($token, ['foo'], 'bar'));
    }

    /**
     * @group legacy
     */
    public function testCacheableVotersLegacy()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersIgnoresNonStringAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', [1337])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->getDecision($token, [1337], 'bar')->isGranted());
    }

    public function testCacheableVotersWithMultipleAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', ['foo', 'bar'])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->getDecision($token, ['foo', 'bar'], 'bar', true)->isGranted());
    }

    public function testCacheableVotersWithEmptyAttributes()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', [])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->getDecision($token, [], 'bar')->isGranted());
    }

    public function testCacheableVotersSupportsMethodsCalledOnce()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->getDecision($token, ['foo'], 'bar')->isGranted());
        $this->assertTrue($manager->getDecision($token, ['foo'], 'bar')->isGranted());
    }

    public function testCacheableVotersNotCalled()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote');

        $manager = new AccessDecisionManager([$voter]);
        $this->assertFalse($manager->getDecision($token, ['foo'], 'bar')->isGranted());
    }

    public function testCacheableVotersWithMultipleAttributesAndNonString()
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this
            ->getMockBuilder(CacheableVoterInterface::class)
            ->setMethods(['getVote', 'supportsAttribute', 'supportsType', 'vote'])
            ->getMock();
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
            ->method('getVote')
            ->with($token, 'bar', ['foo', 1337])
            ->willReturn(Vote::createGranted());

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->getDecision($token, ['foo', 1337], 'bar', true)->isGranted());
    }

    private function getExpectedVoter(Vote $vote): VoterInterface
    {
        $voter = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(['getVote', 'vote'])
            ->getMock();
        $voter->expects($this->once())
            ->method('getVote')
            ->willReturn($vote);

        return $voter;
    }

    private function getExpectedVoterLegacy(int $vote): VoterInterface
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
