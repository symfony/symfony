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
use Symfony\Component\Security\Core\Exception\LogicException;

class AccessDecisionManagerTest extends TestCase
{
    public function provideBadVoterResults(): array
    {
        return [
            [3],
            [true],
        ];
    }

    public function provideDataWithAndWithoutVoteObject()
    {
        yield [
            'useVoteObject' => false,
            'decideFunction' => 'decide',
            'voteFunction' => 'vote',
            'excpectedCallback' => fn ($a) => $a,
        ];

        yield [
            'useVoteObject' => true,
            'decideFunction' => 'getDecision',
            'voteFunction' => 'getVote',
            'excpectedCallback' => fn ($access, $votes = []) => new AccessDecision(
                $access ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
                $votes
            ),
        ];
    }

    public function createVoterMock(bool $useVoteObject)
    {
        return $useVoteObject ?
            $this->getMockBuilder(CacheableVoterInterface::class)
                ->onlyMethods(['supportsAttribute', 'supportsType', 'vote'])
                ->addMethods(['getVote'])
                ->getMock():
            $this->createMock(CacheableVoterInterface::class);
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testVoterCalls($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);

        $voters = [
            $this->getExpectedVoter(VoterInterface::ACCESS_DENIED),
            $this->getExpectedVoter(VoterInterface::ACCESS_GRANTED),
            $this->getUnexpectedVoter(),
        ];

        if($useVoteObject) {
            $strategy = new class() implements AccessDecisionStrategyInterface {
                public function decide(\Traversable $results): bool { throw new LogicException('Method should not be called'); } // never call
                public function getDecision(\Traversable $votes): AccessDecision
                {
                    $i = 0;
                    foreach ($votes as $vote) {
                        switch ($i++) {
                            case 0:
                                Assert::assertSame(VoterInterface::ACCESS_DENIED, $vote->getAccess());

                                break;
                            case 1:
                                Assert::assertSame(VoterInterface::ACCESS_GRANTED, $vote->getAccess());

                                return new AccessDecision(VoterInterface::ACCESS_GRANTED);
                        }
                    }

                    return new AccessDecision(VoterInterface::ACCESS_DENIED);
                }
            };
        } else {
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
        }

        $manager = new AccessDecisionManager($voters, $strategy);

        $this->assertEquals($excpectedCallback(true), $manager->$decideFunction($token, ['ROLE_FOO']));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVoters($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', ['foo'])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, ['foo'], 'bar'));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersIgnoresNonStringAttributes($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', [1337])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, [1337], 'bar'));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersWithMultipleAttributes($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', ['foo', 'bar'])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, ['foo', 'bar'], 'bar', true));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersWithEmptyAttributes($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', [])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, [], 'bar'));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersSupportsMethodsCalledOnce($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', ['foo'])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, ['foo'], 'bar'));
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, ['foo'], 'bar'));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersNotCalled($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(false, []), $manager->$decideFunction($token, ['foo'], 'bar'));
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testCacheableVotersWithMultipleAttributesAndNonString($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $token = $this->createMock(TokenInterface::class);
        $voter = $this->createVoterMock($useVoteObject);

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
            ->method($voteFunction)
            ->with($token, 'bar', ['foo', 1337])
            ->willReturn($vote = ($useVoteObject ? new Vote(VoterInterface::ACCESS_GRANTED) : VoterInterface::ACCESS_GRANTED));

        $manager = new AccessDecisionManager([$voter]);
        $this->assertEquals($excpectedCallback(true, [$vote]), $manager->$decideFunction($token, ['foo', 1337], 'bar', true));
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
