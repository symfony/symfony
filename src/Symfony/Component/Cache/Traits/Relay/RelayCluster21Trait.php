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
    trait RelayCluster21Trait
    {
        public function clusterscan(&$iterator, $match = null, $count = 0, $type = null, $slot = null): array|false
        {
            return $this->initializeLazyObject()->clusterscan($iterator, ...\array_slice(\func_get_args(), 1));
        }

        public function gcra($key, $maxBurst, $requestsPerPeriod, $period, $tokens = 0): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->gcra(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RelayCluster21Trait
    {
    }
}
