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
class TraceableVoter implements CacheableVoterInterface
{
    private VoterInterface $voter;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(VoterInterface $voter, EventDispatcherInterface $eventDispatcher)
    {
        $this->voter = $voter;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
    {
        if (method_exists($this->voter, 'getVote')) {
            $vote = $this->voter->getVote($token, $subject, $attributes);
        } else {
            $result = $this->voter->vote($token, $subject, $attributes);
            $vote = new Vote($result);
        }

        $this->eventDispatcher->dispatch(new VoteEvent($this->voter, $subject, $attributes, $vote), 'debug.security.authorization.vote');

        return $vote;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        trigger_deprecation('symfony/security-core', '6.2', 'Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.', __CLASS__, __CLASS__);

        return $this->getVote($token, $subject, $attributes)->getAccess();
    }

    public function getDecoratedVoter(): VoterInterface
    {
        return $this->voter;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return !$this->voter instanceof CacheableVoterInterface || $this->voter->supportsAttribute($attribute);
    }

    public function supportsType(string $subjectType): bool
    {
        return !$this->voter instanceof CacheableVoterInterface || $this->voter->supportsType($subjectType);
    }
}
