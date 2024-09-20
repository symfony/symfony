<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Event\VoteEvent;

/**
 * Listen to vote events from traceable voters.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class VoteListener implements EventSubscriberInterface
{
    public function __construct(
        private TraceableAccessDecisionManager $traceableAccessDecisionManager,
    ) {
    }

    public function onVoterVote(VoteEvent $event): void
    {
        $this->traceableAccessDecisionManager->addVoterVote($event->getVoter(), $event->getAttributes(), $event->getVote());
    }

    public static function getSubscribedEvents(): array
    {
        return ['debug.security.authorization.vote' => 'onVoterVote'];
    }
}
