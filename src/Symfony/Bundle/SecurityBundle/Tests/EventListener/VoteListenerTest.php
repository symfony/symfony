<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\EventListener\VoteListener;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Event\VoteEvent;

class VoteListenerTest extends TestCase
{
    public function testOnVoterVote()
    {
        $voter = $this->createMock(VoterInterface::class);

        $traceableAccessDecisionManager = $this
            ->getMockBuilder(TraceableAccessDecisionManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addVoterVote'])
            ->getMock();

        $traceableAccessDecisionManager
            ->expects($this->once())
            ->method('addVoterVote')
            ->with($voter, ['myattr1', 'myattr2'], VoterInterface::ACCESS_GRANTED);

        $sut = new VoteListener($traceableAccessDecisionManager);
        $sut->onVoterVote(new VoteEvent($voter, 'mysubject', ['myattr1', 'myattr2'], VoterInterface::ACCESS_GRANTED));
    }
}
