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
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitrangeintarray(...\func_get_args());
        }

        public function bless($command, $key = null, $directive = null, $options = null): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bless(...\func_get_args());
        }

        public function bzmove($source, $destination, $timeout, $options = null): \Relay\Relay|array|false|null
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmove(...\func_get_args());
        }

        public function saddex($key, $members, $options = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->saddex(...\func_get_args());
        }

        public function sexpire($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sexpire(...\func_get_args());
        }

        public function sexpireat($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sexpireat(...\func_get_args());
        }

        public function sexpiretime($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sexpiretime(...\func_get_args());
        }

        public function spersist($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spersist(...\func_get_args());
        }

        public function spexpire($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spexpire(...\func_get_args());
        }

        public function spexpireat($key, $expiry, $members, $exptype = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spexpireat(...\func_get_args());
        }

        public function spexpiretime($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spexpiretime(...\func_get_args());
        }

        public function spttl($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spttl(...\func_get_args());
        }

        public function sttl($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sttl(...\func_get_args());
        }

        public function zmove($source, $destination, $options = null): \Relay\Relay|array|false|null
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmove(...\func_get_args());
        }

        public function zmovemember($source, $destination, $members, $options = null): \Relay\Relay|array|false|null
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmovemember(...\func_get_args());
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
