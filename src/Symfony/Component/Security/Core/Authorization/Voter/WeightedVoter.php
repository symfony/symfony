<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

/**
 * WeightedVoter is an abstract implementation of a weighted voter.
 *
 * @author Thomas Ploch <profiploch@gmail.com>
 */
abstract class WeightedVoter extends Voter implements WeightedVoterInterface
{
    /**
     * {@inheritdoc}
     */
    abstract public function getWeight();
}
