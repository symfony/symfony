<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Attribute;

/**
 * Adds a middleware to a message bus.
 *
 * Without "before" or "after", the middleware runs after the ones listed in the configuration of the bus.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsMessageMiddleware
{
    /**
     * @param string                   $bus    The id of the bus to add the middleware to, or "*" for all buses
     * @param string|list<string>|null $before Middleware of the same bus this one runs before, as configured ids, service ids or classes
     * @param string|list<string>|null $after  Middleware of the same bus this one runs after, as configured ids, service ids or classes
     */
    public function __construct(
        public string $bus,
        public string|array|null $before = null,
        public string|array|null $after = null,
    ) {
    }
}
