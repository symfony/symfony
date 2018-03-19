<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CallableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CallableVoterTest extends TestCase
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVote($callable, $expected)
    {
        $voter = new CallableVoter($callable);
        $tokenMock = $this->getMockBuilder(TokenInterface::class)->getMock();

        $this->assertSame($expected, $voter->vote($tokenMock, null, array()));
    }

    public function getVoteTests()
    {
        return array(
            array(
                function () {
                    return true;
                },
                VoterInterface::ACCESS_GRANTED,
            ),
            array(
                array(
                    new class() {
                        public function get()
                        {
                            return true;
                        }
                    },
                    'get',
                ),
                VoterInterface::ACCESS_GRANTED,
            ),
            array(
                new class() {
                    public function __invoke()
                    {
                        return true;
                    }
                },
                VoterInterface::ACCESS_GRANTED,
            ),
            array(
                function () {
                    return false;
                },
                VoterInterface::ACCESS_DENIED,
            ),
            array(
                array(
                    new class() {
                        public function get()
                        {
                            return false;
                        }
                    },
                    'get',
                ),
                VoterInterface::ACCESS_DENIED,
            ),
            array(
                new class() {
                    public function __invoke()
                    {
                        return false;
                    }
                },
                VoterInterface::ACCESS_DENIED,
            ),
            array(
                function () {
                    return null;
                },
                VoterInterface::ACCESS_ABSTAIN,
            ),
            array(
                array(
                    new class() {
                        public function get()
                        {
                            return null;
                        }
                    },
                    'get',
                ),
                VoterInterface::ACCESS_ABSTAIN,
            ),
            array(
                new class() {
                    public function __invoke()
                    {
                        return null;
                    }
                },
                VoterInterface::ACCESS_ABSTAIN,
            ),
        );
    }
}
