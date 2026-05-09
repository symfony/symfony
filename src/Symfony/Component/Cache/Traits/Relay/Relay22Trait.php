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

if (version_compare(phpversion('relay'), '0.22.0', '>=')) {
    /**
     * @internal
     */
    trait Relay22Trait
    {
        public function jsonStrAppend($key, $value, $path = null, $fpha = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->jsonStrAppend(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay22Trait
    {
        public function jsonStrAppend($key, $value, $path = null): \Relay\Relay|array|false
        {
            return $this->initializeLazyObject()->jsonStrAppend(...\func_get_args());
        }
    }
}
