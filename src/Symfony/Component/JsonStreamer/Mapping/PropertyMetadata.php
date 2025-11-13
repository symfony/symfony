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
 */
final class PropertyMetadata
{
    private ?array $streamToNativeValueTransformers;

    /**
     * @param list<string|\Closure>      $valueTransformers
     * @param list<string|\Closure>|null $streamToNativeValueTransformers
     */
    public function __construct(
        private ?string $name,
        private Type $type,
        private array $valueTransformers = [],
        ?array $streamToNativeValueTransformers = null,
    ) {
        if (null !== $streamToNativeValueTransformers) {
            trigger_deprecation('symfony/json-streamer', '7.4', 'The "streamToNativeValueTransformers" parameter of the "%s()" method is deprecated. Use "valueTransformers" instead.', __METHOD__);
        }

        $this->streamToNativeValueTransformers = $streamToNativeValueTransformers;
    }

    /**
     * @param list<string|\Closure> $valueTransformers
     */
    public static function createSynthetic(Type $type, array $valueTransformers = []): self
    {
        return new self(null, $type, $valueTransformers);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function withName(?string $name): self
    {
        return new self($name, $this->type, $this->valueTransformers, $this->streamToNativeValueTransformers);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function withType(Type $type): self
    {
        return new self($this->name, $type, $this->valueTransformers, $this->streamToNativeValueTransformers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getValueTransformers(): array
    {
        return $this->valueTransformers;
    }

    /**
     * @param list<string|\Closure> $valueTransformers
     */
    public function withValueTransformers(array $valueTransformers): self
    {
        return new self($this->name, $this->type, $valueTransformers);
    }

    public function withAdditionalValueTransformer(string|\Closure $valueTransformer): self
    {
        $valueTransformers = $this->valueTransformers;

        $valueTransformers[] = $valueTransformer;
        $valueTransformers = array_values(array_unique($valueTransformers));

        return $this->withValueTransformers($valueTransformers);
    }

    /**
     * @deprecated since Symfony 7.4, use "getValueTransformers" instead
     *
     * @return list<string|\Closure>
     */
    public function getNativeToStreamValueTransformer(): array
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::getValueTransformers()" instead.', __METHOD__, self::class);

        return $this->valueTransformers;
    }

    /**
     * @deprecated since Symfony 7.4, use "withValueTransformers" instead
     *
     * @param list<string|\Closure> $nativeToStreamValueTransformers
     */
    public function withNativeToStreamValueTransformers(array $nativeToStreamValueTransformers): self
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::withValueTransformers()" instead.', __METHOD__, self::class);

        return new self($this->name, $this->type, $nativeToStreamValueTransformers, $this->streamToNativeValueTransformers);
    }

    /**
     * @deprecated since Symfony 7.4, use "withAdditionalValueTransformer" instead
     */
    public function withAdditionalNativeToStreamValueTransformer(string|\Closure $nativeToStreamValueTransformer): self
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::withAdditionalValueTransformer()" instead.', __METHOD__, self::class);

        $nativeToStreamValueTransformers = $this->valueTransformers;

        $nativeToStreamValueTransformers[] = $nativeToStreamValueTransformer;
        $nativeToStreamValueTransformers = array_values(array_unique($nativeToStreamValueTransformers));

        return $this->withNativeToStreamValueTransformers($nativeToStreamValueTransformers);
    }

    /**
     * @deprecated since Symfony 7.4, use "getValueTransformers" instead
     *
     * @return list<string|\Closure>
     */
    public function getStreamToNativeValueTransformers(): array
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::getValueTransformers()" instead.', __METHOD__, self::class);

        return $this->streamToNativeValueTransformers ?? [];
    }

    /**
     * @deprecated since Symfony 7.4, use "withValueTransformers" instead
     *
     * @param list<string|\Closure> $streamToNativeValueTransformers
     */
    public function withStreamToNativeValueTransformers(array $streamToNativeValueTransformers): self
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::withValueTransformers()" instead.', __METHOD__, self::class);

        return new self($this->name, $this->type, $this->valueTransformers, $streamToNativeValueTransformers);
    }

    /**
     * @deprecated since Symfony 7.4, use "withAdditionalValueTransformer" instead
     */
    public function withAdditionalStreamToNativeValueTransformer(string|\Closure $streamToNativeValueTransformer): self
    {
        trigger_deprecation('symfony/json-streamer', '7.4', 'The "%s()" method is deprecated, use "%s::withAdditionalValueTransformer()" instead.', __METHOD__, self::class);

        $streamToNativeValueTransformers = $this->streamToNativeValueTransformers ?? [];

        $streamToNativeValueTransformers[] = $streamToNativeValueTransformer;
        $streamToNativeValueTransformers = array_values(array_unique($streamToNativeValueTransformers));

        return $this->withStreamToNativeValueTransformers($streamToNativeValueTransformers);
    }
}
