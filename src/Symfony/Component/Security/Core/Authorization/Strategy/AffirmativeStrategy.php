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
 * Grants access if any voter returns an affirmative response.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class AffirmativeStrategy implements AccessDecisionStrategyInterface, \Stringable
{
    public function __construct(
        private bool $allowIfAllAbstainDecisions = false,
    ) {
    }

    public function decide(\Traversable $results): bool
    {
        return $this->getDecision(new \ArrayIterator(array_map(fn ($vote) => new Vote($vote), iterator_to_array($results))))->isGranted();
    }

    public function getDecision(\Traversable $votes): AccessDecision
    {
        $currentVotes = [];
        $deny = 0;

        /** @var Vote $vote */
        foreach ($votes as $vote) {
            $currentVotes[] = $vote;

            if ($vote->isGranted()) {
                return new AccessDecision(VoterInterface::ACCESS_GRANTED, $currentVotes);
            }

            if ($vote->isDenied()) {
                ++$deny;
            }
        }

        if ($deny > 0) {
            return new AccessDecision(VoterInterface::ACCESS_DENIED, $currentVotes);
        }

        return new AccessDecision($this->allowIfAllAbstainDecisions ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $currentVotes);
    }

    public function __toString(): string
    {
        return 'affirmative';
    }
}
