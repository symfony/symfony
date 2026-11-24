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
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
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
        $voters = [
            $this->getExpectedVoter(VoterInterface::ACCESS_DENIED),
            $this->getExpectedVoter(VoterInterface::ACCESS_GRANTED),
            $this->getUnexpectedVoter(),
        ];

        $strategy = new class implements AccessDecisionStrategyInterface {
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

        $this->assertTrue($manager->decide(new NullToken(), ['ROLE_FOO']));
    }

    public function testCacheableVoters()
    {
        $token = new NullToken();
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
        $token = new NullToken();
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
        $token = new NullToken();
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
        $token = new NullToken();
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
        $token = new NullToken();
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
        $token = new NullToken();
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
        $token = new NullToken();
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

    /**
     * @dataProvider provideMultipleAttributesWithRoleVoter
     */
    public function testMultipleAttributesWithRoleVoter(AccessDecisionStrategyInterface $strategy, array $roles, bool $expected)
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);

        $manager = new AccessDecisionManager([new RoleVoter()], $strategy);

        $this->assertSame($expected, $manager->decide($token, ['ROLE_ADMIN', 'ROLE_AUDITOR'], null, true));
    }

    public static function provideMultipleAttributesWithRoleVoter(): iterable
    {
        yield [new UnanimousStrategy(), ['ROLE_ADMIN', 'ROLE_AUDITOR'], true];
        yield [new UnanimousStrategy(), ['ROLE_ADMIN'], false];
        yield [new UnanimousStrategy(), ['ROLE_AUDITOR'], false];
        yield [new UnanimousStrategy(), ['ROLE_USER'], false];

        foreach ([new AffirmativeStrategy(), new ConsensusStrategy(), new PriorityStrategy()] as $strategy) {
            yield [$strategy, ['ROLE_ADMIN', 'ROLE_AUDITOR'], true];
            yield [$strategy, ['ROLE_ADMIN'], true];
            yield [$strategy, ['ROLE_AUDITOR'], true];
            yield [$strategy, ['ROLE_USER'], false];
        }
    }

    public function testUnanimousStrategyAsksVotersAboutEachAttribute()
    {
        $token = $this->createStub(TokenInterface::class);
        $calls = [];

        $voter = $this->createMock(VoterInterface::class);
        $voter
            ->expects($this->exactly(2))
            ->method('vote')
            ->willReturnCallback(static function (TokenInterface $token, $subject, array $attributes) use (&$calls) {
                $calls[] = $attributes;

                return ['foo'] === $attributes ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_ABSTAIN;
            });

        $manager = new AccessDecisionManager([$voter], new UnanimousStrategy());

        $this->assertTrue($manager->decide($token, ['foo', 'bar'], 'subject', true));
        $this->assertSame([['foo'], ['bar']], $calls);
    }

    public function testUnanimousStrategyWithCacheableVoters()
    {
        $token = $this->createStub(TokenInterface::class);
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->exactly(2))
            ->method('supportsAttribute')
            ->willReturnMap([['foo', true], ['bar', false]]);
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

        $manager = new AccessDecisionManager([$voter], new UnanimousStrategy());
        $this->assertTrue($manager->decide($token, ['foo', 'bar'], 'bar', true));
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
            private int $vote;

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
