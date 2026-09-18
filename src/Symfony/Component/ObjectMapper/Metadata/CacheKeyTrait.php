<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * @internal
 */
trait CacheKeyTrait
{
    private function encodeClass(string $class): string
    {
        return rawurlencode(strtr($class, '\\', '_'));
    }
}
