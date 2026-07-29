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

if (version_compare(phpversion('relay'), '0.40.0', '>=')) {
    /**
     * @internal
     */
    trait RelayCluster40Trait
    {
        public function blmovem($srckey, $dstkey, $srcpos, $dstpos, $timeout, $options = null): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->blmovem(...\func_get_args());
        }

        public function getDbNum(): mixed
        {
            return $this->initializeLazyObject()->getDbNum(...\func_get_args());
        }

        public function lmovem($srckey, $dstkey, $srcpos, $dstpos, $options = null): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->lmovem(...\func_get_args());
        }

        public function move($key, $db): \Relay\Cluster|false|int
        {
            return $this->initializeLazyObject()->move(...\func_get_args());
        }

        public function sdiffcard($keys, $options = null): \Relay\Cluster|false|int
        {
            return $this->initializeLazyObject()->sdiffcard(...\func_get_args());
        }

        public function select($db): \Relay\Cluster|bool|string
        {
            return $this->initializeLazyObject()->select(...\func_get_args());
        }

        public function sunioncard($keys, $options = null): \Relay\Cluster|false|int
        {
            return $this->initializeLazyObject()->sunioncard(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RelayCluster40Trait
    {
    }
}
