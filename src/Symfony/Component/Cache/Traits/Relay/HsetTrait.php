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
    trait HsetTrait
    {
        public function hset($key, ...$keys_and_vals): \Relay\Relay|false|int
        {
            return $this->initializeLazyObject()->hset(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait HsetTrait
    {
        public function hset($key, $mem, $val, ...$kvals): \Relay\Relay|false|int
        {
            return $this->initializeLazyObject()->hset(...\func_get_args());
        }
    }
}
