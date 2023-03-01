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
class ServerVariable implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param string[]|null $enum
     */
    public function __construct(
        private readonly string $default,
        private readonly ?string $description = null,
        private readonly ?array $enum = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string[]|null
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'enum' => $this->getEnum(),
            'default' => $this->getDefault(),
            'description' => $this->getDescription(),
        ] + $this->getSpecificationExtensions());
    }
}
