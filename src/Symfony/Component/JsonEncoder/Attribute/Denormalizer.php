<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Attribute;

/**
 * Defines a callable or a {@see \Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface} service id
 * that will be used to denormalize the property data during decoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Denormalizer
{
    private string|\Closure $denormalizer;

    /**
     * @param string|(callable(mixed, array<string, mixed>?): mixed)|(callable(mixed): mixed) $denormalizer
     */
    public function __construct(mixed $denormalizer)
    {
        if (\is_callable($denormalizer)) {
            $denormalizer = \Closure::fromCallable($denormalizer);
        }

        $this->denormalizer = $denormalizer;
    }

    public function getDenormalizer(): string|\Closure
    {
        return $this->denormalizer;
    }
}
