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

/**
 * The AuthorizationCheckerInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuthorizationCheckerInterface
{
    /**
     * Checks if the attribute is granted against the current authentication token and optionally supplied subject.
     *
     * @param mixed               $attribute      A single attribute to vote on (can be of any type; strings, Expression and Closure instances are supported by the core)
     * @param AccessDecision|null $accessDecision Should be used to explain the decision
     */
    public function isGranted(mixed $attribute, mixed $subject = null/* , ?AccessDecision $accessDecision = null */): bool;
}
