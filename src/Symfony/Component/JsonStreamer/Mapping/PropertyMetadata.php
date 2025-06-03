<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping;

use Symfony\Component\TypeInfo\Type;

/**
 * Holds stream reading/writing metadata about a given property.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class PropertyMetadata
{
    /**
     * @param list<string|\Closure> $nativeToStreamValueTransformers
     * @param list<string|\Closure> $streamToNativeValueTransformers
     */
    public function __construct(
        private string $name,
        private Type $type,
        private array $nativeToStreamValueTransformers = [],
        private array $streamToNativeValueTransformers = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->nativeToStreamValueTransformers, $this->streamToNativeValueTransformers);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function withType(Type $type): self
    {
        return new self($this->name, $type, $this->nativeToStreamValueTransformers, $this->streamToNativeValueTransformers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getNativeToStreamValueTransformer(): array
    {
        return $this->nativeToStreamValueTransformers;
    }

    /**
     * @param list<string|\Closure> $nativeToStreamValueTransformers
     */
    public function withNativeToStreamValueTransformers(array $nativeToStreamValueTransformers): self
    {
        return new self($this->name, $this->type, $nativeToStreamValueTransformers, $this->streamToNativeValueTransformers);
    }

    public function withAdditionalNativeToStreamValueTransformer(string|\Closure $nativeToStreamValueTransformer): self
    {
        $nativeToStreamValueTransformers = $this->nativeToStreamValueTransformers;

        $nativeToStreamValueTransformers[] = $nativeToStreamValueTransformer;
        $nativeToStreamValueTransformers = array_values(array_unique($nativeToStreamValueTransformers));

        return $this->withNativeToStreamValueTransformers($nativeToStreamValueTransformers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getStreamToNativeValueTransformers(): array
    {
        return $this->streamToNativeValueTransformers;
    }

    /**
     * @param list<string|\Closure> $streamToNativeValueTransformers
     */
    public function withStreamToNativeValueTransformers(array $streamToNativeValueTransformers): self
    {
        return new self($this->name, $this->type, $this->nativeToStreamValueTransformers, $streamToNativeValueTransformers);
    }

    public function withAdditionalStreamToNativeValueTransformer(string|\Closure $streamToNativeValueTransformer): self
    {
        $streamToNativeValueTransformers = $this->streamToNativeValueTransformers;

        $streamToNativeValueTransformers[] = $streamToNativeValueTransformer;
        $streamToNativeValueTransformers = array_values(array_unique($streamToNativeValueTransformers));

        return $this->withStreamToNativeValueTransformers($streamToNativeValueTransformers);
    }
}
