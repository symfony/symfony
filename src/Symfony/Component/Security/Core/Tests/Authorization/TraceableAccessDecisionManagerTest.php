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
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog(array $expectedLog, array $attributes, $object, array $voterVotes, bool $result)
    {
        $token = $this->createMock(TokenInterface::class);

        $admMock = $this
            ->getMockBuilder(AccessDecisionManager::class)
            ->setMethods(['decide'])
            ->getMock();

        $adm = new TraceableAccessDecisionManager($admMock);

        $admMock
            ->expects($this->once())
            ->method('decide')
            ->with($token, $attributes, $object)
            ->willReturnCallback(function ($token, $attributes, $object) use ($voterVotes, $adm, $result) {
                foreach ($voterVotes as $voterVote) {
                    list($voter, $vote) = $voterVote;
                    $adm->addVoterVote($voter, $attributes, $vote);
                }

                return $result;
            })
        ;

        $adm->decide($token, $attributes, $object);

        $this->assertEquals($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs(): \Generator
    {
        $voter1 = $this->getMockForAbstractClass(VoterInterface::class);
        $voter2 = $this->getMockForAbstractClass(VoterInterface::class);

        yield [
            [[
                'attributes' => ['ATTRIBUTE_1'],
                'object' => null,
                'result' => true,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1'], 'vote' => VoterInterface::ACCESS_GRANTED],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1'], 'vote' => VoterInterface::ACCESS_GRANTED],
                ],
            ]],
            ['ATTRIBUTE_1'],
            null,
            [
                [$voter1, VoterInterface::ACCESS_GRANTED],
                [$voter2, VoterInterface::ACCESS_GRANTED],
            ],
            true,
        ];
        yield [
            [[
                'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
                'object' => true,
                'result' => false,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_1', 'ATTRIBUTE_2'], 'vote' => VoterInterface::ACCESS_GRANTED],
                ],
            ]],
            ['ATTRIBUTE_1', 'ATTRIBUTE_2'],
            true,
            [
                [$voter1, VoterInterface::ACCESS_ABSTAIN],
                [$voter2, VoterInterface::ACCESS_GRANTED],
            ],
            false,
        ];
        yield [
            [[
                'attributes' => [null],
                'object' => 'jolie string',
                'result' => false,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [null], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => [null], 'vote' => VoterInterface::ACCESS_DENIED],
                ],
            ]],
            [null],
            'jolie string',
            [
                [$voter1, VoterInterface::ACCESS_ABSTAIN],
                [$voter2, VoterInterface::ACCESS_DENIED],
            ],
            false,
        ];
        yield [
            [[
                'attributes' => [12],
                'object' => 12345,
                'result' => true,
                'voterDetails' => [],
            ]],
            'attributes' => [12],
            12345,
            [],
            true,
        ];
        yield [
            [[
                'attributes' => [new \stdClass()],
                'object' => $x = fopen(__FILE__, 'rb'),
                'result' => true,
                'voterDetails' => [],
            ]],
            [new \stdClass()],
            $x,
            [],
            true,
        ];
        yield [
            [[
                'attributes' => ['ATTRIBUTE_2'],
                'object' => $x = [],
                'result' => false,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['ATTRIBUTE_2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['ATTRIBUTE_2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                ],
            ]],
            ['ATTRIBUTE_2'],
            $x,
            [
                [$voter1, VoterInterface::ACCESS_ABSTAIN],
                [$voter2, VoterInterface::ACCESS_ABSTAIN],
            ],
            false,
        ];
        yield [
            [[
                'attributes' => [12.13],
                'object' => new \stdClass(),
                'result' => false,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => [12.13], 'vote' => VoterInterface::ACCESS_DENIED],
                    ['voter' => $voter2, 'attributes' => [12.13], 'vote' => VoterInterface::ACCESS_DENIED],
                ],
            ]],
            [12.13],
            new \stdClass(),
            [
                [$voter1, VoterInterface::ACCESS_DENIED],
                [$voter2, VoterInterface::ACCESS_DENIED],
            ],
            false,
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
                $vote = \in_array('attr1', $attributes) ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_ABSTAIN;
                $sut->addVoterVote($voter1, $attributes, $vote);

                return $vote;
            });

        $voter2
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter2) {
                if (\in_array('attr2', $attributes)) {
                    $vote = null == $subject ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED;
                } else {
                    $vote = VoterInterface::ACCESS_ABSTAIN;
                }

                $sut->addVoterVote($voter2, $attributes, $vote);

                return $vote;
            });

        $voter3
            ->expects($this->any())
            ->method('vote')
            ->willReturnCallback(function (TokenInterface $token, $subject, array $attributes) use ($sut, $voter3) {
                if (\in_array('attr2', $attributes) && $subject) {
                    $vote = $sut->decide($token, $attributes);
                } else {
                    $vote = VoterInterface::ACCESS_ABSTAIN;
                }

                $sut->addVoterVote($voter3, $attributes, $vote);

                return $vote;
            });

        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $sut->decide($token, ['attr1'], null);
        $sut->decide($token, ['attr2'], $obj = new \stdClass());

        $this->assertEquals([
            [
                'attributes' => ['attr1'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr1'], 'vote' => VoterInterface::ACCESS_GRANTED],
                ],
                'result' => true,
            ],
            [
                'attributes' => ['attr2'],
                'object' => null,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => VoterInterface::ACCESS_GRANTED],
                ],
                'result' => true,
            ],
            [
                'attributes' => ['attr2'],
                'object' => $obj,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['attr2'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['attr2'], 'vote' => VoterInterface::ACCESS_DENIED],
                    ['voter' => $voter3, 'attributes' => ['attr2'], 'vote' => VoterInterface::ACCESS_GRANTED],
                ],
                'result' => true,
            ],
        ], $sut->getDecisionLog());
    }
}
