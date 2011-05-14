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

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * UnanimousAccessStrategy Grants access if only grant (or abstain) votes were
 * received.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class UnanimousAccessStrategy extends AccessStrategy implements AccessStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        foreach ($attributes as $attribute) {
            foreach ($this->voters as $voter) {
                $result = $voter->vote($token, $object, array($attribute));

                switch ($result) {
                    case VoterInterface::ACCESS_GRANTED:
                        ++$grant;

                        break;

                    case VoterInterface::ACCESS_DENIED:
                        return false;

                    default:
                        break;
                }
            }
        }

        // no deny votes
        if ($grant > 0) {
            return true;
        }

        return $this->allowIfAllAbstainDecisions;
    }
}