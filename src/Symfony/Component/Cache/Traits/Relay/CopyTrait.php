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

if (version_compare(phpversion('relay'), '0.8.1', '>=')) {
    /**
     * @internal
     */
    trait CopyTrait
    {
        public function copy($src, $dst, $options = null): \Relay\Relay|bool
        {
            return $this->initializeLazyObject()->copy(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait CopyTrait
    {
        public function copy($src, $dst, $options = null): \Relay\Relay|false|int
        {
            return $this->initializeLazyObject()->copy(...\func_get_args());
        }
    }
}
