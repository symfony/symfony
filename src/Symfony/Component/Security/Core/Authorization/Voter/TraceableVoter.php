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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\VoteEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $vote = $this->voter->vote($token, $subject, $attributes);
        $result = $vote;
        if (!$vote instanceof Vote && \in_array($vote, [VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_ABSTAIN])) {
            trigger_deprecation('symfony/security-core', '5.3', 'Returning an int from "%s::vote" is deprecated, return an instance of "%s" instead.', \get_class($this->voter), Vote::class);

            $result = Vote::create($vote);
        }

        $this->eventDispatcher->dispatch(new VoteEvent($this->voter, $subject, $attributes, $vote), 'debug.security.authorization.vote');

        return $result;
    }

    public function getDecoratedVoter(): VoterInterface
    {
        return $this->voter;
    }
}
