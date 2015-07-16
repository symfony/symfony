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
        $this->allowIfAllAbstainDecisions = (bool) $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = (bool) $allowIfEqualGrantedDeniedDecisions;
        $this->strategy = $this->createStrategy($strategy);
        $this->setVoters($voters);
    }

    /**
     * Configures the voters.
     *
     * @param VoterInterface[] $voters An array of VoterInterface instances
     */
    public function setVoters(array $voters)
    {
        $this->strategy->setVoters($voters);
    }

    private function createStrategy($strategyName)
    {
        switch($strategyName){
            case self::STRATEGY_UNANIMOUS:
                return new DecideUnanimousStrategy($this->allowIfAllAbstainDecisions);
            case self::STRATEGY_CONSENSUS:
                return new DecideConsensusStrategy($this->allowIfEqualGrantedDeniedDecisions,$this->allowIfAllAbstainDecisions);
            case self::STRATEGY_AFFIRMATIVE:
                return new DecideAffirmativeStrategy($this->allowIfAllAbstainDecisions);
            case self::STRATEGY_HIGHEST_NOT_ABSTAINED:
                return new DecideHighestNotAbstainedVoterStrategy($this->allowIfAllAbstainDecisions);
            default:
                throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategyName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        return $this->strategy->decide($token, $attributes, $object);
    }


    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $this->strategy->supportsClass($class);
    }

    /**
     * Checks if the access decision manager supports the given attribute.
     *
     * @param string $attribute An attribute
     *
     * @return bool true if this decision manager supports the attribute, false otherwise
     */
    public function supportsAttribute($attribute)
    {
        return $this->strategy->supportsAttribute($attribute);
    }


}
