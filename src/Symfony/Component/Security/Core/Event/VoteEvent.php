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

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * This event is dispatched on voter vote.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class VoteEvent extends Event
{
    private $voter;
    private $subject;
    private $attributes;
    private $vote;

    public function __construct(VoterInterface $voter, $subject, array $attributes, int $vote)
    {
        $this->voter = $voter;
        $this->subject = $subject;
        $this->attributes = $attributes;
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

    public function getVote(): int
    {
        return $this->vote;
    }
}
