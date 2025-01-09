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

use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;
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
     * @param VoterInterface::ACCESS_*|int  $access
     * @param VoteInterface[]               $votes
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

    public function isAbstainer(): bool
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
     * @return VoteInterface[]
     */
    public function getVotes(): array
    {
        return $this->votes;
    }

    /**
     * @return VoteInterface[]
     */
    public function getGrantedVotes(): array
    {
        return $this->getVotesByAccess(VoterInterface::ACCESS_GRANTED);
    }

    /**
     * @return VoteInterface[]
     */
    public function getAbstainerVotes(): array
    {
        return $this->getVotesByAccess(VoterInterface::ACCESS_ABSTAIN);
    }

    /**
     * @return VoteInterface[]
     */
    public function getDeniedVotes(): array
    {
        return $this->getVotesByAccess(VoterInterface::ACCESS_DENIED);
    }

    /**
     * @return VoteInterface[]
     */
    private function getVotesByAccess(int $access): array
    {
        return array_filter($this->votes, static fn ($vote) => $vote->getAccess() === $access);
    }
}
