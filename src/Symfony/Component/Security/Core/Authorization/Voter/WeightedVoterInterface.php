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
 * WeightedVoterInterface is the interface implemented by voters that have a higher weight in decisions.
 *
 * @author Thomas Ploch <profiploch@gmail.com>
 */
interface WeightedVoterInterface extends VoterInterface
{
    /**
     * This method provides the weight used to come to a weighted authorization decision.
     *
     * The weight has to be an integer value >= 1.
     *
     * @return int
     */
    public function getWeight();
}
