<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access if only grant (or abstain) votes were received.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Antoine Lamirault <lamiraultantoine@gmail.com>
 */
final class UnanimousStrategy implements AccessDecisionStrategyInterface, \Stringable
{
    private bool $allowIfAllAbstainDecisions;

    public function __construct(bool $allowIfAllAbstainDecisions = false)
    {
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    public function getDecision(\Traversable $votes): AccessDecision
    {
        $currentVotes = [];
        $grant = 0;

        /** @var Vote $vote */
        foreach ($votes as $vote) {
            $currentVotes[] = $vote;
            if ($vote->isDenied()) {
                return AccessDecision::createDenied($currentVotes);
            }

            if ($vote->isGranted()) {
                ++$grant;
            }
        }

        // no deny votes
        if ($grant > 0) {
            return AccessDecision::createGranted($currentVotes);
        }

        return $this->allowIfAllAbstainDecisions
            ? AccessDecision::createGranted($currentVotes)
            : AccessDecision::createDenied($currentVotes)
        ;
    }

    public function decide(\Traversable $results): bool
    {
        trigger_deprecation('symfony/security-core', '6.3', 'Method "%s::decide()" has been deprecated, use "%s::getDecision()" instead.', __CLASS__, __CLASS__);

        $grant = 0;
        foreach ($results as $result) {
            if (VoterInterface::ACCESS_DENIED === $result) {
                return false;
            }

            if (VoterInterface::ACCESS_GRANTED === $result) {
                ++$grant;
            }
        }

        // no deny votes
        if ($grant > 0) {
            return true;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    public function __toString(): string
    {
        return 'unanimous';
    }
}
