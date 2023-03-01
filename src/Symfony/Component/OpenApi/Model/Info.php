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
class Info implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly string $title,
        private readonly string $version,
        private readonly ?string $summary = null,
        private readonly ?string $description = null,
        private readonly ?string $termsOfService = null,
        private readonly ?Contact $contact = null,
        private readonly ?License $license = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTermsOfService(): ?string
    {
        return $this->termsOfService;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'termsOfService' => $this->getTermsOfService(),
            'contact' => $this->getContact()?->toArray(),
            'license' => $this->getLicense()?->toArray(),
            'version' => $this->getVersion(),
            'summary' => $this->getSummary(), // ??
        ] + $this->getSpecificationExtensions());
    }
}
