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
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Event\VoteEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TraceableVoterTest extends TestCase
{
    public function testGetDecoratedVoterClass()
    {
        $voter = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $sut = new TraceableVoter($voter, self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass());
        self::assertSame($voter, $sut->getDecoratedVoter());
    }

    public function testVote()
    {
        $voter = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();
        $token = self::getMockBuilder(TokenInterface::class)->getMockForAbstractClass();

        $voter
            ->expects(self::once())
            ->method('vote')
            ->with($token, 'anysubject', ['attr1'])
            ->willReturn(VoterInterface::ACCESS_DENIED);

        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(new VoteEvent($voter, 'anysubject', ['attr1'], VoterInterface::ACCESS_DENIED), 'debug.security.authorization.vote');

        $sut = new TraceableVoter($voter, $eventDispatcher);
        $result = $sut->vote($token, 'anysubject', ['attr1']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testSupportsAttributeOnCacheable()
    {
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();

        $voter
            ->expects(self::once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);

        $sut = new TraceableVoter($voter, $eventDispatcher);

        self::assertFalse($sut->supportsAttribute('foo'));
    }

    public function testSupportsTypeOnCacheable()
    {
        $voter = self::getMockBuilder(CacheableVoterInterface::class)->getMockForAbstractClass();
        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();

        $voter
            ->expects(self::once())
            ->method('supportsType')
            ->with('foo')
            ->willReturn(false);

        $sut = new TraceableVoter($voter, $eventDispatcher);

        self::assertFalse($sut->supportsType('foo'));
    }

    public function testSupportsAttributeOnNonCacheable()
    {
        $voter = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();
        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();

        $sut = new TraceableVoter($voter, $eventDispatcher);

        self::assertTrue($sut->supportsAttribute('foo'));
    }

    public function testSupportsTypeOnNonCacheable()
    {
        $voter = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();
        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();

        $sut = new TraceableVoter($voter, $eventDispatcher);

        self::assertTrue($sut->supportsType('foo'));
    }
}
