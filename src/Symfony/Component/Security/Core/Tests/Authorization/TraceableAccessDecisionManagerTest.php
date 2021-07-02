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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog(array $expectedLog, array $attributes, $object, array $voterVotes, $result)
    {
        $token = $this->createMock(TokenInterface::class);

        $admMock = $this->createMock(AccessDecisionManager::class);

        $adm = new TraceableAccessDecisionManager($admMock);

        $admMock
            ->expects($this->once())
            ->method('getDecision')
            ->with($token, $attributes, $object)
            ->willReturnCallback(function ($token, $attributes, $object) use ($voterVotes, $adm, $result) {
                foreach ($voterVotes as $voterVote) {
                    [$voter, $vote] = $voterVote;
                    $adm->addVoterVote($voter, $attributes, $vote);
                }

                return $result;
            })
        ;

        $adm->getDecision($token, $attributes, $object);

        $this->assertSame($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs(): \Generator
    {
        $voter1 = $this->getMockForAbstractClass(VoterInterface::class);
        $voter2 = $this->getMockForAbstractClass(VoterInterface::class);

        yield [
            [[
                'attributes' => ['ATTRIBUTE_1'],
                'object' => null,
                'result' => ($result = AccessDecision::createGranted()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1'], 'vote' => $granted_vote1 = Vote::createGranted()],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1'], 'vote' => $granted_vote2 = Vote::createGranted()],
                ],
            ]],
            ['ATTRIBUTE_1'],
            null,
            [
                [$voter1, $granted_vote1],
                [$voter2, $granted_vote2],
            ],
            $result,
        ];

        yield [
            [[
                'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
                'object' => null,
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => $granted_vote2 = Vote::createGranted()],
                ],
            ]],
            ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
            null,
            [
                [$voter1, $abstain_vote1],
                [$voter2, $granted_vote2],
            ],
            $result,
        ];

        yield [
            [[
                'attributes' => [null],
                'object' => null,
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [null], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => [null], 'vote' => $granted_vote2 = Vote::createGranted()],
                ],
            ]],
            [null],
            null,
            [
                [$voter1, $abstain_vote1],
                [$voter2, $granted_vote2],
            ],
            $result,
        ];

        yield [
            [[
                'attributes' => [12],
                'object' => null,
                'result' => ($result = AccessDecision::createGranted()),
                'voterDetails' => [],
            ]],
            'attributes' => [12],
            null,
            [],
            $result,
        ];

        yield [
            [[
                'attributes' => [new \stdClass()],
                'object' => null,
                'result' => ($result = AccessDecision::createGranted()),
                'voterDetails' => [],
            ]],
            [new \stdClass()],
            null,
            [],
            $result,
        ];

        yield [
            [[
                'attributes' => ['ATTRIBUTE_2'],
                'object' => null,
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_2'], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_2'], 'vote' => $abstain_vote2 = Vote::createAbstain()],
                ],
            ]],
            ['ATTRIBUTE_2'],
            null,
            [
                [$voter1, $abstain_vote1],
                [$voter2, $abstain_vote2],
            ],
            $result,
        ];

        yield [
            [[
                'attributes' => [12.13],
                'object' => ($x = new \stdClass()),
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [12.13], 'vote' => $denied_vote1 = Vote::createDenied()],
                    ['voter' => $voter2, 'attributes' => [12.13], 'vote' => $denied_vote2 = Vote::createDenied()],
                ],
            ]],
            [12.13],
            $x,
            [
                [$voter1, $denied_vote1],
                [$voter2, $denied_vote2],
            ],
            $result,
        ];
    }

    public function testDebugAccessDecisionManagerAliasExistsForBC()
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());

        $this->assertInstanceOf(DebugAccessDecisionManager::class, $adm, 'For BC, TraceableAccessDecisionManager must be an instance of DebugAccessDecisionManager');
    }

    /**
     * Tests decision log returned when a voter call decide method of AccessDecisionManager.
     */
    public function testAccessDecisionManagerCalledByVoter()
    {
        $voter1 = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(['vote'])
            ->getMock();

        $voter2 = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(['vote'])
            ->getMock();

        $voter3 = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(['vote'])
            ->getMock();

        $sut = new TraceableAccessDecisionManager(new AccessDecisionManager([$voter1, $voter2, $voter3]));

        $voter1
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter1) {
                $vote = \in_array('attr1', $attributes) ? Vote::createGranted() : Vote::createAbstain();
                $sut->addVoterVote($voter1, $attributes, $vote);

                return $vote;
            });

        $voter2
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter2) {
                if (\in_array('attr2', $attributes)) {
                    $vote = null === $subject ? Vote::createGranted() : Vote::createDenied();
                } else {
                    $vote = Vote::createAbstain();
                }

                $sut->addVoterVote($voter2, $attributes, $vote);

                return $vote;
            });

        $voter3
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter3) {
                if (\in_array('attr2', $attributes) && $subject) {
                    $decision = $sut->getDecision($token, $attributes);
                    if ($decision->isGranted()) {
                        $vote = Vote::createGranted();
                    } elseif ($decision->isAbstain()) {
                        $vote = Vote::createAbstain();
                    } else {
                        $vote = Vote::createDenied();
                    }
                } else {
                    $vote = Vote::createAbstain();
                }

                $sut->addVoterVote($voter3, $attributes, $vote);

                return $vote;
            });

        $token = $this->createMock(TokenInterface::class);
        $sut->getDecision($token, ['attr1'], null);
        $sut->getDecision($token, ['attr2'], $obj = new \stdClass());

        $this->assertEquals([
            [
                'attributes' => ['attr1'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr1'], 'vote' => Vote::createGranted()],
                ],
                'result' => true,
            ],
            [
                'attributes' => ['attr2'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => Vote::createGranted()],
                ],
                'result' => true,
            ],
            [
                'attributes' => ['attr2'],
                'object' => $obj,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => Vote::createDenied()],
                    ['voter' => $voter3, 'attributes' => ['attr2'], 'vote' => Vote::createGranted()],
                ],
                'result' => true,
            ],
        ], $sut->getDecisionLog());
    }
}
