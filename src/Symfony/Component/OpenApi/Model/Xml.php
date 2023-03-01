<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Model;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class Xml implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $namespace = null,
        private readonly ?string $prefix = null,
        private readonly ?bool $attribute = null,
        private readonly ?bool $wrapped = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function isAttribute(): ?bool
    {
        return $this->attribute;
    }

    public function isWrapped(): ?bool
    {
        return $this->wrapped;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->getName(),
            'namespace' => $this->getNamespace(),
            'prefix' => $this->getPrefix(),
            'attribute' => $this->isAttribute(),
            'wrapped' => $this->isWrapped(),
        ] + $this->getSpecificationExtensions());
    }
}
