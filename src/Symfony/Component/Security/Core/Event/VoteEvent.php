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
    private $voter;
    private $subject;
    private $attributes;
    private $vote;

    /**
     * @param Vote|int $vote
     */
    public function __construct(VoterInterface $voter, $subject, array $attributes, $vote)
    {
        $this->voter = $voter;
        $this->subject = $subject;
        $this->attributes = $attributes;
        if (!$vote instanceof Vote) {
            trigger_deprecation('symfony/security-core', '5.3', 'Passing an int as the fourth argument to "%s::__construct" is deprecated, pass a "%s" instance instead.', __CLASS__, Vote::class);

            $vote = Vote::create($vote);
        }

        $this->vote = $vote;
    }

    public function getVoter(): VoterInterface
    {
        return $this->voter;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getVote(): Vote
    {
        return $this->vote;
    }
}
