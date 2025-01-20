<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping;

use Symfony\Component\TypeInfo\Type;

/**
 * Holds encoding/decoding metadata about a given property.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class PropertyMetadata
{
    /**
     * @param list<string|\Closure> $normalizers
     * @param list<string|\Closure> $denormalizers
     */
    public function __construct(
        private string $name,
        private Type $type,
        private array $normalizers = [],
        private array $denormalizers = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->normalizers, $this->denormalizers);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function withType(Type $type): self
    {
        return new self($this->name, $type, $this->normalizers, $this->denormalizers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getNormalizers(): array
    {
        return $this->normalizers;
    }

    /**
     * @param list<string|\Closure> $normalizers
     */
    public function withNormalizers(array $normalizers): self
    {
        return new self($this->name, $this->type, $normalizers, $this->denormalizers);
    }

    public function withAdditionalNormalizer(string|\Closure $normalizer): self
    {
        $normalizers = $this->normalizers;

        $normalizers[] = $normalizer;
        $normalizers = array_values(array_unique($normalizers));

        return $this->withNormalizers($normalizers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getDenormalizers(): array
    {
        return $this->denormalizers;
    }

    /**
     * @param list<string|\Closure> $denormalizers
     */
    public function withDenormalizers(array $denormalizers): self
    {
        return new self($this->name, $this->type, $this->normalizers, $denormalizers);
    }

    public function withAdditionalDenormalizer(string|\Closure $denormalizer): self
    {
        $denormalizers = $this->denormalizers;

        $denormalizers[] = $denormalizer;
        $denormalizers = array_values(array_unique($denormalizers));

        return $this->withDenormalizers($denormalizers);
    }
}
