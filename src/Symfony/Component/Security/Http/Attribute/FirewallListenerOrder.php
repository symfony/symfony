<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

/**
 * Declares the firewall listeners a listener runs before or after.
 *
 * The priority returned by getPriority() is kept: these constraints only reorder the listeners that share it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FirewallListenerOrder
{
    /**
     * @param string|list<string>|null $before Listeners this one runs before, as service ids or classes
     * @param string|list<string>|null $after  Listeners this one runs after, as service ids or classes
     */
    public function __construct(
        public string|array|null $before = null,
        public string|array|null $after = null,
    ) {
    }
}
