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
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Tests\Fixtures\DummyVoter;

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog(array $expectedLog, array $attributes, $object, array $voterVotes, AccessDecision $decision)
    {
        $token = $this->createMock(TokenInterface::class);
        $admMock = $this
            ->getMockBuilder(AccessDecisionManagerInterface::class)
            ->setMethods(['getDecision', 'decide'])
            ->getMock();

        $adm = new TraceableAccessDecisionManager($admMock);

        $admMock
            ->expects($this->once())
            ->method('getDecision')
            ->with($token, $attributes, $object)
            ->willReturnCallback(function ($token, $attributes, $object) use ($voterVotes, $adm, $decision) {
                foreach ($voterVotes as $voterVote) {
                    [$voter, $vote] = $voterVote;
                    $adm->addVoterVote($voter, $attributes, $vote);
                }

                return $decision;
            })
        ;

        $adm->getDecision($token, $attributes, $object);

        $this->assertEquals($expectedLog, $adm->getDecisionLog());
    }

    public static function provideObjectsAndLogs(): \Generator
    {
        $voter1 = new DummyVoter();
        $voter2 = new DummyVoter();

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
                'object' => true,
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => $granted_vote2 = Vote::createGranted()],
                ],
            ]],
            ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
            true,
            [
                [$voter1, $abstain_vote1],
                [$voter2, $granted_vote2],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [null],
                'object' => 'jolie string',
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [null], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => [null], 'vote' => $abstain_denied2 = Vote::createAbstain()],
                ],
            ]],
            [null],
            'jolie string',
            [
                [$voter1, $abstain_vote1],
                [$voter2, $abstain_denied2],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [12],
                'object' => 12345,
                'result' => ($result = AccessDecision::createGranted()),
                'voterDetails' => [],
            ]],
            'attributes' => [12],
            12345,
            [],
            $result,
        ];
        yield [
            [[
                'attributes' => [new \stdClass()],
                'object' => $x = fopen(__FILE__, 'r'),
                'result' => ($result = AccessDecision::createGranted()),
                'voterDetails' => [],
            ]],
            [new \stdClass()],
            $x,
            [],
            $result,
        ];
        yield [
            [[
                'attributes' => ['ATTRIBUTE_2'],
                'object' => $x = [],
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_2'], 'vote' => $abstain_vote1 = Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_2'], 'vote' => $abstain_vote2 = Vote::createAbstain()],
                ],
            ]],
            ['ATTRIBUTE_2'],
            $x,
            [
                [$voter1, $abstain_vote1],
                [$voter2, $abstain_vote2],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [12.13],
                'object' => new \stdClass(),
                'result' => ($result = AccessDecision::createDenied()),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [12.13], 'vote' => $denied_vote1 = Vote::createDenied()],
                    ['voter' => $voter2, 'attributes' => [12.13], 'vote' => $denied_vote2 = Vote::createDenied()],
                ],
            ]],
            [12.13],
            new \stdClass(),
            [
                [$voter1, $denied_vote1],
                [$voter2, $denied_vote2],
            ],
            $result,
        ];
    }

    /**
     * Tests decision log returned when a voter call decide method of AccessDecisionManager.
     */
    public function testAccessDecisionManagerCalledByVoter()
    {
        $voter1 = $this
            ->getMockBuilder(VoterInterface::class)
            ->onlyMethods(['getVote', 'vote'])
            ->getMock();

        $voter2 = $this
            ->getMockBuilder(VoterInterface::class)
            ->onlyMethods(['getVote', 'vote'])
            ->getMock();

        $voter3 = $this
            ->getMockBuilder(VoterInterface::class)
            ->onlyMethods(['getVote', 'vote'])
            ->getMock();

        $sut = new TraceableAccessDecisionManager(new AccessDecisionManager([$voter1, $voter2, $voter3]));

        $voter1
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter1) {
                $vote = \in_array('attr1', $attributes) ? Vote::createGranted() : Vote::createAbstain();
                $sut->addVoterVote($voter1, $attributes, $vote);

                return $vote;
            });

        $voter2
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter2) {
                if (\in_array('attr2', $attributes)) {
                    $vote = null == $subject ? Vote::createGranted() : Vote::createDenied();
                } else {
                    $vote = Vote::createAbstain();
                }

                $sut->addVoterVote($voter2, $attributes, $vote);

                return $vote;
            });

        $voter3
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter3) {
                if (\in_array('attr2', $attributes) && $subject) {
                    $vote = $sut->getDecision($token, $attributes)->isGranted() ? Vote::createGranted() : Vote::createDenied();
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
                'result' => AccessDecision::createGranted([
                    Vote::createGranted(),
                ]),
            ],
            [
                'attributes' => ['attr2'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => Vote::createGranted()],
                ],
                'result' => AccessDecision::createGranted([
                    Vote::createAbstain(),
                    Vote::createGranted(),
                ]),
            ],
            [
                'attributes' => ['attr2'],
                'object' => $obj,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => Vote::createAbstain()],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => Vote::createDenied()],
                    ['voter' => $voter3, 'attributes' => ['attr2'], 'vote' => Vote::createGranted()],
                ],
                'result' => AccessDecision::createGranted([
                    Vote::createAbstain(),
                    Vote::createDenied(),
                    Vote::createGranted(),
                ]),
            ],
        ], $sut->getDecisionLog());
    }

    public function testCustomAccessDecisionManagerReturnsEmptyStrategy()
    {
        $admMock = $this->createMock(AccessDecisionManagerInterface::class);

        $adm = new TraceableAccessDecisionManager($admMock);

        $this->assertEquals('-', $adm->getStrategy());
    }
}
