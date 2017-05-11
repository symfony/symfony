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

class_exists(TraceableAccessDecisionManager::class);

if (false) {
    /**
     * This is a placeholder for the old class, that got renamed; this is not a BC break since the class is internal, this
     * placeholder is here just to help backward compatibility with older SecurityBundle versions.
     *
     * @deprecated The DebugAccessDecisionManager class has been renamed and is deprecated since version 3.3 and will be removed in 4.0. Use the TraceableAccessDecisionManager class instead.
     *
     * @internal
     */
    class DebugAccessDecisionManager implements AccessDecisionManagerInterface
    {
        /**
         * {@inheritdoc}
         */
        public function decide(TokenInterface $token, array $attributes, $object = null)
        {
        }
    }
}
