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

if (version_compare(phpversion('relay'), '0.9.0', '>=')) {
    /**
     * @internal
     */
    trait PfcountTrait
    {
        public function pfcount($key_or_keys): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait PfcountTrait
    {
        public function pfcount($key): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount(...\func_get_args());
        }
    }
}
