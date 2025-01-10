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

class TraceableAccessDecisionManagerWithVoteObjectTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog(array $expectedLog, array $attributes, $object, array $voterVotes, AccessDecision $decision)
    {
        $token = $this->createMock(TokenInterface::class);
        $admMock = $this
            ->getMockBuilder(AccessDecisionManagerInterface::class)
            ->onlyMethods(['decide'])
            ->addMethods(['getDecision'])
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
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_GRANTED),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                ],
            ]],
            ['ATTRIBUTE_1'],
            null,
            [
                [$voter1, new Vote(VoterInterface::ACCESS_GRANTED)],
                [$voter2, new Vote(VoterInterface::ACCESS_GRANTED)],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
                'object' => true,
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_DENIED),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                ],
            ]],
            ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
            true,
            [
                [$voter1, VoterInterface::ACCESS_ABSTAIN],
                [$voter2, new Vote(VoterInterface::ACCESS_GRANTED)],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [null],
                'object' => 'jolie string',
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_DENIED),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [null], 'vote' => new Vote(VoterInterface::ACCESS_ABSTAIN)],
                    ['voter' => $voter2, 'attributes' => [null], 'vote' => new Vote(VoterInterface::ACCESS_DENIED)],
                ],
            ]],
            [null],
            'jolie string',
            [
                [$voter1, new Vote(VoterInterface::ACCESS_ABSTAIN)],
                [$voter2, new Vote(VoterInterface::ACCESS_DENIED)],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [12],
                'object' => 12345,
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_GRANTED),
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
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_GRANTED),
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
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_DENIED),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_2'], 'vote' => new Vote(VoterInterface::ACCESS_ABSTAIN)],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_2'], 'vote' => new Vote(VoterInterface::ACCESS_ABSTAIN)],
                ],
            ]],
            ['ATTRIBUTE_2'],
            $x,
            [
                [$voter1, new Vote(VoterInterface::ACCESS_ABSTAIN)],
                [$voter2, new Vote(VoterInterface::ACCESS_ABSTAIN)],
            ],
            $result,
        ];
        yield [
            [[
                'attributes' => [12.13],
                'object' => new \stdClass(),
                'result' => $result = new AccessDecision(VoterInterface::ACCESS_DENIED),
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [12.13], 'vote' => new Vote(VoterInterface::ACCESS_DENIED)],
                    ['voter' => $voter2, 'attributes' => [12.13], 'vote' => new Vote(VoterInterface::ACCESS_DENIED)],
                ],
            ]],
            [12.13],
            new \stdClass(),
            [
                [$voter1, new Vote(VoterInterface::ACCESS_DENIED)],
                [$voter2, new Vote(VoterInterface::ACCESS_DENIED)],
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
            ->onlyMethods(['vote'])
            ->addMethods(['getVote'])
            ->getMock();

        $voter2 = $this
            ->getMockBuilder(VoterInterface::class)
            ->onlyMethods(['vote'])
            ->addMethods(['getVote'])
            ->getMock();

        $voter3 = $this
            ->getMockBuilder(VoterInterface::class)
            ->onlyMethods(['vote'])
            ->addMethods(['getVote'])
            ->getMock();

        $sut = new TraceableAccessDecisionManager(new AccessDecisionManager([$voter1, $voter2, $voter3]));

        $voter1
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter1) {
                $vote = new Vote(\in_array('attr1', $attributes, true) ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_ABSTAIN);
                $sut->addVoterVote($voter1, $attributes, $vote);

                return $vote;
            });

        $voter2
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter2) {
                if (\in_array('attr2', $attributes, true)) {
                    $vote = new Vote((null == $subject) ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED);
                } else {
                    $vote = new Vote(VoterInterface::ACCESS_ABSTAIN);
                }

                $sut->addVoterVote($voter2, $attributes, $vote);

                return $vote;
            });

        $voter3
            ->expects($this->any())
            ->method('getVote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter3) {
                if (\in_array('attr2', $attributes, true) && $subject) {
                    $vote = new Vote($sut->getDecision($token, $attributes)->isGranted() ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED);
                } else {
                    $vote = new Vote(VoterInterface::ACCESS_ABSTAIN);
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
                    ['voter' => $voter1, 'attributes' => ['attr1'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                ],
                'result' => new AccessDecision(VoterInterface::ACCESS_GRANTED, [new Vote(VoterInterface::ACCESS_GRANTED)]),
            ],
            [
                'attributes' => ['attr2'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => new Vote(VoterInterface::ACCESS_ABSTAIN)],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                ],
                'result' => new AccessDecision(VoterInterface::ACCESS_GRANTED, [
                    new Vote(VoterInterface::ACCESS_ABSTAIN),
                    new Vote(VoterInterface::ACCESS_GRANTED),
                ]),
            ],
            [
                'attributes' => ['attr2'],
                'object' => $obj,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => new Vote(VoterInterface::ACCESS_ABSTAIN)],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => new Vote(VoterInterface::ACCESS_DENIED)],
                    ['voter' => $voter3, 'attributes' => ['attr2'], 'vote' => new Vote(VoterInterface::ACCESS_GRANTED)],
                ],
                'result' => new AccessDecision(VoterInterface::ACCESS_GRANTED, [
                    new Vote(VoterInterface::ACCESS_ABSTAIN),
                    new Vote(VoterInterface::ACCESS_DENIED),
                    new Vote(VoterInterface::ACCESS_GRANTED),
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
