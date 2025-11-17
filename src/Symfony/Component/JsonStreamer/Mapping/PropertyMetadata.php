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
    /**
     * @param list<string|\Closure> $valueTransformers
     */
    public function __construct(
        private ?string $name,
        private Type $type,
        private array $valueTransformers = [],
    ) {
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
        return new self($name, $this->type, $this->valueTransformers);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function withType(Type $type): self
    {
        return new self($this->name, $type, $this->valueTransformers);
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
}
