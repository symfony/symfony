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

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AccessDecisionManager is the base class for all access decision managers
 * that use decision voters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccessDecisionManager implements AccessDecisionManagerInterface
{
    const STRATEGY_AFFIRMATIVE = 'affirmative';
    const STRATEGY_CONSENSUS = 'consensus';
    const STRATEGY_UNANIMOUS = 'unanimous';

    private $voters;
    private $defaultStrategyMethod;
    private $allowIfAllAbstainDecisions;
    private $allowIfEqualGrantedDeniedDecisions;

    /**
     * @var array
     */
    private $strategyResolvers;

    /**
     * Constructor.
     *
     * @param VoterInterface[]            $voters                             An array of VoterInterface instances
     * @param string                      $defaultStrategy                    The vote default strategy
     * @param bool                        $allowIfAllAbstainDecisions         Whether to grant access if all voters abstained or not
     * @param bool                        $allowIfEqualGrantedDeniedDecisions Whether to grant access if result are equals
     * @param StrategyResolverInterface[] $strategyResolvers                  An array of StrategyResolver instances
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $voters = array(), $defaultStrategy = self::STRATEGY_AFFIRMATIVE, $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true, array $strategyResolvers = array())
    {
        $defaultStrategyMethod = $this->getStrategyMethod($defaultStrategy);
        if (!is_callable(array($this, $defaultStrategyMethod))) {
            throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $defaultStrategy));
        }

        $this->voters = $voters;
        $this->defaultStrategyMethod = $defaultStrategyMethod;
        $this->allowIfAllAbstainDecisions = (bool) $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = (bool) $allowIfEqualGrantedDeniedDecisions;
    }

    /**
     * Configures the voters.
     *
     * @param VoterInterface[] $voters An array of VoterInterface instances
     */
    public function setVoters(array $voters)
    {
        $this->voters = $voters;
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $strategyMethod = $this->defaultStrategyMethod;
        /* @var $strategyResolver StrategyResolverInterface */
        foreach ($this->strategyResolvers as $strategyResolver) {
            if ($strategyResolver->supports($token, $attributes, $object)) {
                $resolvedStrategy = $strategyResolver->getStrategy($token, $attributes, $object);
                if (!is_string($resolvedStrategy)) {
                    continue;
                }

                $resolvedStrategyMethod = $this->getStrategyMethod($resolvedStrategy);
                if (!is_callable(array($this, $resolvedStrategyMethod))) {
                    continue;
                }

                $strategyMethod = $resolvedStrategyMethod;

                break;
            }
        }

        return $this->{$strategyMethod}($token, $attributes, $object);
    }

    /**
     * Grants access if any voter returns an affirmative response.
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideAffirmative(TokenInterface $token, array $attributes, $object = null)
    {
        $deny = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);
            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    return true;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;

                default:
                    break;
            }
        }

        if ($deny > 0) {
            return false;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    /**
     * Grants access if there is consensus of granted against denied responses.
     *
     * Consensus means majority-rule (ignoring abstains) rather than unanimous
     * agreement (ignoring abstains). If you require unanimity, see
     * UnanimousBased.
     *
     * If there were an equal number of grant and deny votes, the decision will
     * be based on the allowIfEqualGrantedDeniedDecisions property value
     * (defaults to true).
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideConsensus(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        $deny = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);

            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    ++$grant;

                    break;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;
            }
        }

        if ($grant > $deny) {
            return true;
        }

        if ($deny > $grant) {
            return false;
        }

        if ($grant > 0) {
            return $this->allowIfEqualGrantedDeniedDecisions;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    /**
     * Grants access if only grant (or abstain) votes were received.
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideUnanimous(TokenInterface $token, array $attributes, $object = null)
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

    /**
     * @param string $strategy
     *
     * @return string
     */
    private function getStrategyMethod($strategy)
    {
        return 'decide' . ucfirst($strategy);
    }
}
