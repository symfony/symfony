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
    trait NullableReturnTrait
    {
        public function dump($key): \Relay\Relay|false|string|null
        {
            return $this->initializeLazyObject()->dump(...\func_get_args());
        }

        public function geodist($key, $src, $dst, $unit = null): \Relay\Relay|false|float|null
        {
            return $this->initializeLazyObject()->geodist(...\func_get_args());
        }

        public function hrandfield($hash, $options = null): \Relay\Relay|array|false|string|null
        {
            return $this->initializeLazyObject()->hrandfield(...\func_get_args());
        }

        public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Relay|false|string|null
        {
            return $this->initializeLazyObject()->xadd(...\func_get_args());
        }

        public function zrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int|null
        {
            return $this->initializeLazyObject()->zrank(...\func_get_args());
        }

        public function zrevrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int|null
        {
            return $this->initializeLazyObject()->zrevrank(...\func_get_args());
        }

        public function zscore($key, $member): \Relay\Relay|false|float|null
        {
            return $this->initializeLazyObject()->zscore(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait NullableReturnTrait
    {
        public function dump($key): \Relay\Relay|false|string
        {
            return $this->initializeLazyObject()->dump(...\func_get_args());
        }

        public function geodist($key, $src, $dst, $unit = null): \Relay\Relay|false|float
        {
            return $this->initializeLazyObject()->geodist(...\func_get_args());
        }

        public function hrandfield($hash, $options = null): \Relay\Relay|array|false|string
        {
            return $this->initializeLazyObject()->hrandfield(...\func_get_args());
        }

        public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Relay|false|string
        {
            return $this->initializeLazyObject()->xadd(...\func_get_args());
        }

        public function zrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int
        {
            return $this->initializeLazyObject()->zrank(...\func_get_args());
        }

        public function zrevrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int
        {
            return $this->initializeLazyObject()->zrevrank(...\func_get_args());
        }

        public function zscore($key, $member): \Relay\Relay|false|float
        {
            return $this->initializeLazyObject()->zscore(...\func_get_args());
        }
    }
}
