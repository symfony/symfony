<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits\Relay;

if (version_compare(phpversion('relay'), '0.61.0', '>=')) {
    /**
     * @internal
     */
    trait Relay61Trait
    {
        public function reconnect(): bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->reconnect(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay61Trait
    {
    }
}
