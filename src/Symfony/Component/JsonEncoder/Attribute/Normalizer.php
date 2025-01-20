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
 * Defines a callable or a {@see \Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface} service id
 * that will be used to normalize the property data during encoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Normalizer
{
    private string|\Closure $normalizer;

    /**
     * @param string|(callable(mixed, array<string, mixed>?): mixed)|(callable(mixed): mixed) $normalizer
     */
    public function __construct(mixed $normalizer)
    {
        if (\is_callable($normalizer)) {
            $normalizer = \Closure::fromCallable($normalizer);
        }

        $this->normalizer = $normalizer;
    }

    public function getNormalizer(): string|\Closure
    {
        return $this->normalizer;
    }
}
