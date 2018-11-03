<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\VoteEvent;

/**
 * Decorates voter classes to send result events.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class TraceableVoter implements VoterInterface
{
    private $voter;
    private $eventDispatcher;

    public function __construct(VoterInterface $voter, EventDispatcherInterface $eventDispatcher)
    {
        $this->voter = $voter;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = $this->voter->vote($token, $subject, $attributes);

        $this->eventDispatcher->dispatch('debug.security.authorization.vote', new VoteEvent($this->voter, $subject, $attributes, $result));

        return $result;
    }

    public function getDecoratedVoter(): VoterInterface
    {
        return $this->voter;
    }
}
