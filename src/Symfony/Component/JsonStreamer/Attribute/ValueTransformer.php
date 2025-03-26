<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Attribute;

use Symfony\Component\JsonStreamer\Exception\LogicException;

/**
 * Defines a callable or a {@see \Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface} service id
 * that will be used to transform the property data during stream reading/writing.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValueTransformer
{
    private \Closure|string|null $streamToNative;
    private \Closure|string|null $nativeToStream;

    /**
     * @param (callable(mixed, array<string, mixed>=): mixed)|string|null $streamToNative
     * @param (callable(mixed, array<string, mixed>=): mixed)|string|null $nativeToStream
     */
    public function __construct(
        callable|string|null $streamToNative = null,
        callable|string|null $nativeToStream = null,
    ) {
        if (!$streamToNative && !$nativeToStream) {
            throw new LogicException('#[ValueTransformer] attribute must declare either $streamToNative or $nativeToStream.');
        }

        if (\is_callable($streamToNative)) {
            $streamToNative = $streamToNative(...);
        }

        if (\is_callable($nativeToStream)) {
            $nativeToStream = $nativeToStream(...);
        }

        $this->streamToNative = $streamToNative;
        $this->nativeToStream = $nativeToStream;
    }

    public function getStreamToNative(): string|\Closure|null
    {
        return $this->streamToNative;
    }

    public function getNativeToStream(): string|\Closure|null
    {
        return $this->nativeToStream;
    }
}
