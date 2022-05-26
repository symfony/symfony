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
 * Grant or deny access depending on the first voter that does not abstain.
 * The priority of voters can be used to overrule a decision.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Antoine Lamirault <lamiraultantoine@gmail.com>
 */
final class PriorityStrategy implements AccessDecisionStrategyInterface, \Stringable
{
    private bool $allowIfAllAbstainDecisions;

    public function __construct(bool $allowIfAllAbstainDecisions = false)
    {
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    public function getDecision(\Traversable $votes): AccessDecision
    {
        $currentVotes = [];

        /** @var Vote $vote */
        foreach ($votes as $vote) {
            $currentVotes[] = $vote;
            if ($vote->isGranted()) {
                return AccessDecision::createGranted($currentVotes);
            }

            if ($vote->isDenied()) {
                return AccessDecision::createDenied($currentVotes);
            }
        }

        return $this->allowIfAllAbstainDecisions
            ? AccessDecision::createGranted($currentVotes)
            : AccessDecision::createDenied($currentVotes)
        ;
    }

    public function decide(\Traversable $results): bool
    {
        trigger_deprecation('symfony/security-core', '6.2', 'Method "%s::decide()" has been deprecated, use "%s::getDecision()" instead.', __CLASS__, __CLASS__);

        foreach ($results as $result) {
            if (VoterInterface::ACCESS_GRANTED === $result) {
                return true;
            }

            if (VoterInterface::ACCESS_DENIED === $result) {
                return false;
            }
        }

        return $this->allowIfAllAbstainDecisions;
    }

    public function __toString(): string
    {
        return 'priority';
    }
}
