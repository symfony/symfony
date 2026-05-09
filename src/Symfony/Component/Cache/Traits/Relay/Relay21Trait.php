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

if (version_compare(phpversion('relay'), '0.21.0', '>=')) {
    /**
     * @internal
     */
    trait Relay21Trait
    {
        public function gcra($key, $maxBurst, $requestsPerPeriod, $period, $tokens = 0): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->gcra(...\func_get_args());
        }

        public function hotkeys($subcmd, $args = null): \Relay\Relay|array|bool
        {
            return $this->initializeLazyObject()->hotkeys(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay21Trait
    {
    }
}
