<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * An AccessDecision is returned by an AccessDecisionManager and contains the access verdict and all the related votes.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
final class AccessDecision
{
    /**
     * @param int $access One of the VoterInterface constants (ACCESS_GRANTED, ACCESS_ABSTAIN, ACCESS_DENIED)
     * @param Vote[] $votes
     */
    public function __construct(
        private readonly int $access,
        private readonly array $votes = [],
        private readonly string $message = '',
    ) {
    }

    public function getAccess(): int
    {
        return $this->access;
    }

    public function isGranted(): bool
    {
        return VoterInterface::ACCESS_GRANTED === $this->access;
    }

    public function isAbstain(): bool
    {
        return VoterInterface::ACCESS_ABSTAIN === $this->access;
    }

    public function isDenied(): bool
    {
        return VoterInterface::ACCESS_DENIED === $this->access;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return Vote[]
     */
    public function getVotes(): array
    {
        return $this->votes;
    }

    /**
     * @return Vote[]
     */
    public function getGrantedVotes(): array
    {
        return $this->getVotesByAccess(Voter::ACCESS_GRANTED);
    }

    /**
     * @return Vote[]
     */
    public function getAbstainedVotes(): array
    {
        return $this->getVotesByAccess(Voter::ACCESS_ABSTAIN);
    }

    /**
     * @return Vote[]
     */
    public function getDeniedVotes(): array
    {
        return $this->getVotesByAccess(Voter::ACCESS_DENIED);
    }

    /**
     * @return Vote[]
     */
    private function getVotesByAccess(int $access): array
    {
        return array_filter($this->votes, static fn (Vote $vote): bool => $vote->getAccess() === $access);
    }
}
