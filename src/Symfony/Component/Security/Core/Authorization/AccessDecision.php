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

use Symfony\Component\Security\Core\Authorization\Voter\AccessTrait;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * An AccessDecision is returned by an AccessDecisionManager and contains the access verdict and all the related votes.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
final class AccessDecision
{
    use AccessTrait;

    /** @var Vote[] */
    private $votes = [];

    /**
     * @param int    $access One of the VoterInterface::ACCESS_* constants
     * @param Vote[] $votes
     */
    private function __construct(int $access, array $votes = [])
    {
        $this->access = $access;
        $this->votes = $votes;
    }

    /**
     * @param Vote[] $votes
     */
    public static function createGranted(array $votes = []): self
    {
        return new self(VoterInterface::ACCESS_GRANTED, $votes);
    }

    /**
     * @param Vote[] $votes
     */
    public static function createDenied(array $votes = []): self
    {
        return new self(VoterInterface::ACCESS_DENIED, $votes);
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
        return array_filter($this->votes, static function (Vote $vote) use ($access): bool {
            return $vote->getAccess() === $access;
        });
    }
}
