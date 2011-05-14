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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AccessStrategyInterface is the base interface to define behaviors for access
 * decision strategies. Access decision strategies are used by the
 * AccessDecisionManager service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
interface AccessStrategyInterface
{
    /**
     * Returns an array of VoterInterface objects.
     *
     * @return VoterInterface[] An array of VoterInterface objects.
     */
    public function getVoters();

    /**
     * Decides which access must be applied for a given TokenInterface object.
     *
     * @return int One of the constant of the VoterInterface interface.
     */
    public function decide(TokenInterface $token, array $attributes, $object = null);
}