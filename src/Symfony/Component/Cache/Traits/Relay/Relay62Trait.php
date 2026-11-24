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

if (version_compare(phpversion('relay'), '0.62.0', '>=')) {
    /**
     * @internal
     */
    trait Relay62Trait
    {
        public function bitrangeintarray($key, $start, $end, $options = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->bitrangeintarray(...\func_get_args());
        }

        public function bless($command, $key = null, $directive = null, $options = null): mixed
        {
            return $this->initializeLazyObject()->bless(...\func_get_args());
        }

        public function bzmove($source, $destination, $timeout, $options = null): \Relay\Relay|array|false|null
        {
            return $this->initializeLazyObject()->bzmove(...\func_get_args());
        }

        public function saddex($key, $members, $options = null): \Relay\Relay|false|int
        {
            return $this->initializeLazyObject()->saddex(...\func_get_args());
        }

        public function sexpire($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->sexpire(...\func_get_args());
        }

        public function sexpireat($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->sexpireat(...\func_get_args());
        }

        public function sexpiretime($key, $members): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->sexpiretime(...\func_get_args());
        }

        public function spersist($key, $members): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->spersist(...\func_get_args());
        }

        public function spexpire($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->spexpire(...\func_get_args());
        }

        public function spexpireat($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->spexpireat(...\func_get_args());
        }

        public function spexpiretime($key, $members): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->spexpiretime(...\func_get_args());
        }

        public function spttl($key, $members): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->spttl(...\func_get_args());
        }

        public function sttl($key, $members): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->sttl(...\func_get_args());
        }

        public function zmove($source, $destination, $options = null): \Relay\Relay|array|false|null
        {
            return $this->initializeLazyObject()->zmove(...\func_get_args());
        }

        public function zmovemember($source, $destination, $members, $options = null): \Relay\Relay|array|false|null
        {
            return $this->initializeLazyObject()->zmovemember(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay62Trait
    {
    }
}
