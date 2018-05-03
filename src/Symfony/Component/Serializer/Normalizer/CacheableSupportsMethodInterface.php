<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

/**
 * Marker interface for normalizers and denormalizers that use
 * only the type and the format in their supports*() methods.
 *
 * By implementing this interface, the return value of the
 * supports*() methods will be cached by type and format.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface CacheableSupportsMethodInterface
{
    public function hasCacheableSupportsMethod(): bool;
}
