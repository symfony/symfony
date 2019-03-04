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
    private $traceableAccessDecisionManager;

    public function __construct(TraceableAccessDecisionManager $traceableAccessDecisionManager)
    {
        $this->traceableAccessDecisionManager = $traceableAccessDecisionManager;
    }

    /**
     * Event dispatched by a voter during access manager decision.
     *
     * @param VoteEvent $event event with voter data
     */
    public function onVoterVote(VoteEvent $event)
    {
        $this->traceableAccessDecisionManager->addVoterVote($event->getVoter(), $event->getAttributes(), $event->getVote());
    }

    public static function getSubscribedEvents()
    {
        return ['debug.security.authorization.vote' => 'onVoterVote'];
    }
}
