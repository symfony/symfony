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

use Symfony\Component\Security\Core\Authorization\Strategy\AccessStrategyInterface;
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
    private $strategy;

    /**
     * Constructor.
     *
     * @param AccessStrategyInterface $strategy The access decision strategy
     */
    public function __construct(AccessStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
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
    public function supportsAttribute($attribute)
    {
        foreach ($this->strategy->getVoters() as $voter) {
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
        foreach ($this->strategy->getVoters() as $voter) {
            if ($voter->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }
}
