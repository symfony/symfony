<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Event;

use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched on voter vote.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
final class VoteEvent extends Event
{
    private VoteInterface $vote;

    public function __construct(
        private VoterInterface $voter,
        private mixed $subject,
        private array $attributes,
        VoteInterface|int $vote,
    ) {
        $this->vote = $vote instanceof VoteInterface ? $vote : new Vote($vote);
    }

    public function getVoter(): VoterInterface
    {
        return $this->voter;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getVote(): int
    {
        return $this->vote->getAccess();
    }

    public function getVoteObject(): VoteInterface
    {
        return $this->vote;
    }
}
