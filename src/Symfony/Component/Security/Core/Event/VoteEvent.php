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
    private VoterInterface $voter;
    private mixed $subject;
    private array $attributes;
    private Vote|int $vote;

    public function __construct(VoterInterface $voter, mixed $subject, array $attributes, Vote|int $vote)
    {
        $this->voter = $voter;
        $this->subject = $subject;
        $this->attributes = $attributes;

        if (!$vote instanceof Vote) {
            trigger_deprecation('symfony/security-core', '6.2', 'Passing an int as the fourth argument to "%s::__construct" is deprecated, pass a "%s" instance instead.', __CLASS__, Vote::class);

            $vote = new Vote($vote);
        }
        $this->vote = $vote;
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

    /**
     * @deprecated since Symfony 6.2, use {@see getVoteDecision()} instead.
     */
    public function getVote(): int
    {
        trigger_deprecation('symfony/security-core', '6.2', 'Method "%s::getVote()" has been deprecated, use "%s::getVoteDecision()" instead.', __CLASS__, __CLASS__);

        return $this->vote->getAccess();
    }

    public function getVoteDecision(): Vote
    {
        return $this->vote;
    }
}
