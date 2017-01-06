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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

interface StrategyResolverInterface
{
    /**
     * This method must return one of the following constants from the AccessDecisionManager :
     * STRATEGY_AFFIRMATIVE, STRATEGY_CONSENSUS, or STRATEGY_UNANIMOUS.
     *
     * @param TokenInterface $token
     * @param array $attributes
     * @param mixed $object
     *
     * @return string
     */
    public function getStrategy(TokenInterface $token, array $attributes, $object = null);

    /**
     * @param TokenInterface $token
     * @param array $attributes
     * @param mixed $object
     *
     * @return bool
     */
    public function supports(TokenInterface $token, array $attributes, $object = null);
}
