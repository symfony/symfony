<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter\Decorator;

use InvalidArgumentException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\WeightedVoterInterface;

/**
 * A decorator to decorate existing voters with the weighted feature.
 *
 * @author Thomas Ploch <profiploch@gmail.com>
 */
final class Weight implements WeightedVoterInterface
{
    private $voter;
    private $weight;

    /**
     * Weight constructor.
     *
     * @param VoterInterface $voter
     * @param int            $weight
     *
     * @throws InvalidArgumentException
     */
    public function __construct(VoterInterface $voter, $weight)
    {
        $this->voter = $voter;
        $this->weight = (int) $weight;

        if ($this->weight < 1) {
            throw new InvalidArgumentException(sprintf('Weight decorator for class "%s" needs to have an integer weight >= 1', get_class($this->voter)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        return $this->voter->vote($token, $subject, $attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function getWeight()
    {
        return $this->weight;
    }
}
