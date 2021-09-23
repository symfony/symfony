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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Event\VoteEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TraceableVoterTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testGetDecoratedVoterClass()
    {
        $voter = $this->getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $sut = new TraceableVoter($voter, $this->getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass());
        $this->assertSame($voter, $sut->getDecoratedVoter());
    }

    public function testVote()
    {
        $voter = $this->getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();
        $token = $this->getMockBuilder(TokenInterface::class)->getMockForAbstractClass();

        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'anysubject', ['attr1'])
            ->willReturn($vote = Vote::createDenied());

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new VoteEvent($voter, 'anysubject', ['attr1'], $vote), 'debug.security.authorization.vote');

        $sut = new TraceableVoter($voter, $eventDispatcher);
        $result = $sut->vote($token, 'anysubject', ['attr1']);

        $this->assertSame($vote, $result);
    }

    /**
     * @group legacy
     */
    public function testVoteLegacy()
    {
        $voter = $this->getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();
        $token = $this->getMockBuilder(TokenInterface::class)->getMockForAbstractClass();

        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'anysubject', ['attr1'])
            ->willReturn(VoterInterface::ACCESS_DENIED);

        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(new VoteEvent($voter, 'anysubject', ['attr1'], VoterInterface::ACCESS_DENIED), 'debug.security.authorization.vote');

        $sut = new TraceableVoter($voter, $eventDispatcher);

        $this->expectDeprecation(sprintf('Since symfony/security-core 5.3: Returning an int from "%s::vote" is deprecated, return an instance of "%s" instead.', \get_class($voter), Vote::class));

        $result = $sut->vote($token, 'anysubject', ['attr1']);

        $this->assertInstanceOf(Vote::class, $result);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result->getAccess());
    }
}
