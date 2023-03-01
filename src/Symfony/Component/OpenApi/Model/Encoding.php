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
class Encoding implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Parameter|Reference>|null $headers
     */
    public function __construct(
        private readonly ?string $contentType = null,
        private readonly ?array $headers = null,
        private readonly ?string $style = null,
        private readonly ?bool $explode = null,
        private readonly ?bool $allowReserved = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    /**
     * @return array<string, Parameter|Reference>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function isExplode(): ?bool
    {
        return $this->explode;
    }

    public function allowReserved(): ?bool
    {
        return $this->allowReserved;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'contentType' => $this->getContentType(),
            'headers' => $this->normalizeCollection($this->getHeaders()),
            'style' => $this->getStyle(),
            'explode' => $this->isExplode(),
            'allowReserved' => $this->allowReserved(),
        ] + $this->getSpecificationExtensions());
    }
}
