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
class CallbackRequest
{
    public function __construct(
        private readonly string $expression,
        private readonly PathItem|Reference|null $definition,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getDefinition(): Reference|PathItem|null
    {
        return $this->definition;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'expression' => $this->getExpression(),
            'definition' => $this->getDefinition()?->toArray(),
        ] + $this->getSpecificationExtensions());
    }
}
