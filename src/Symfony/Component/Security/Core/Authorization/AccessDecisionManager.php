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

use Symfony\Component\Security\Core\Authorization\Strategy\DecideAffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\DecideConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\DecideHighestNotAbstainedVoterStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\DecideUnanimousStrategy;
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
    const STRATEGY_HIGHEST_NOT_ABSTAINED = 'highest';

    private $voters;
    private $strategies;
    private $strategy;
    private $allowIfAllAbstainDecisions;
    private $allowIfEqualGrantedDeniedDecisions;

    /**
     * Constructor.
     *
     * @param VoterInterface[] $voters                             An array of VoterInterface instances
     * @param string           $strategy                           The vote strategy
     * @param bool             $allowIfAllAbstainDecisions         Whether to grant access if all voters abstained or not
     * @param bool             $allowIfEqualGrantedDeniedDecisions Whether to grant access if result are equals
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $voters = array(), $strategy = self::STRATEGY_AFFIRMATIVE, $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
    {
        $this->strategies = array();
        $this->voters = $voters;
        $this->strategy = $strategy;
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
     * @param mixed $strategies
     */
    public function addStrategy($name,$strategy)
    {
        $this->strategies[$name] = $strategy;
    }

    private function getStrategy($strategyName)
    {
        if(!array_key_exists($strategyName,$this->strategies))
        {
            switch($strategyName){
                case self::STRATEGY_UNANIMOUS:
                    return new DecideUnanimousStrategy();
                case self::STRATEGY_CONSENSUS:
                    return new DecideConsensusStrategy();
                case self::STRATEGY_AFFIRMATIVE:
                    return new DecideAffirmativeStrategy();
                case self::STRATEGY_HIGHEST_NOT_ABSTAINED:
                    return new DecideHighestNotAbstainedVoterStrategy();
                default:
                    break;
            }
        } elseif($this->strategies[$strategyName] instanceof AccessDecisionStrategyInterface) {
            return $this->strategies[$strategyName];
        }

        throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategyName));
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $strategy = $this->getStrategy($this->strategy);
        $strategy->setVoters($this->voters);
        $strategy->setAllowIfAllAbstainDecisions($this->allowIfAllAbstainDecisions);
        $strategy->setAllowIfEqualGrantedDeniedDecisions($this->allowIfEqualGrantedDeniedDecisions);

        return $strategy->decide($token, $attributes, $object);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }
}
