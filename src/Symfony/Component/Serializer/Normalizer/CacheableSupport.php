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
 * The return value of supports*() methods in {@see NormalizerInterface} and {@see DenormalizerInterface}.
 * Tells if the result should be cached based on type and format.
 *
 * -1 : no, never supports the $format+$type, cache it
 * 0  : no, no cache
 * 1  : yes, no cache
 * 2  : yes, always supports the $format+$type, cache it
 *
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
enum CacheableSupport: int
{
    case SupportNever = -1;
    case SupportNot = 0;
    case Support = 1;
    case SupportAlways = 2;

    public function supports(): bool
    {
        return match ($this) {
            CacheableSupport::SupportNever, CacheableSupport::SupportNot => false,
            CacheableSupport::Support, CacheableSupport::SupportAlways => true,
        };
    }
}
