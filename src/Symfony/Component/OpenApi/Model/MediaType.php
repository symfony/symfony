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
class MediaType implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Example|Reference>|null $examples
     * @param array<string, Encoding>|null          $encodings
     */
    public function __construct(
        private readonly Schema|Reference|null $schema = null,
        private readonly mixed $example = null,
        private readonly ?array $examples = null,
        private readonly ?array $encodings = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getSchema(): Schema|Reference|null
    {
        return $this->schema;
    }

    public function getExample(): mixed
    {
        return $this->example;
    }

    /**
     * @return array<string, Example|Reference>|null
     */
    public function getExamples(): ?array
    {
        return $this->examples;
    }

    /**
     * @return array<string, Encoding>|null
     */
    public function getEncodings(): ?array
    {
        return $this->encodings;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'schema' => $this->getSchema()?->toArray(),
            'example' => $this->getExample(),
            'examples' => $this->normalizeCollection($this->getExamples()),
            'encoding' => $this->normalizeCollection($this->getEncodings()),
        ] + $this->getSpecificationExtensions());
    }
}
