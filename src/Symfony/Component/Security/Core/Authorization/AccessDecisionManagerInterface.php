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

/**
 * AccessDecisionManagerInterface makes authorization decisions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @method AccessDecision getDecision(TokenInterface $token, array $attributes, object $object = null)
 */
interface AccessDecisionManagerInterface
{
    /**
     * Decides whether the access is possible or not.
     *
     * @param array  $attributes An array of attributes associated with the method being invoked
     * @param object $object     The object to secure
     *
     * @return bool true if the access is granted, false otherwise
     *
     * @deprecated since 5.3, use {@see getDecision()} instead.
     */
    public function decide(TokenInterface $token, array $attributes, $object = null): bool;
}
