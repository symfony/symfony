<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Extractor;

use Symfony\Component\AutoMapper\Transformer\TransformerInterface;

/**
 * Property mapping.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class PropertyMapping
{
    private $readAccessor;

    private $writeMutator;

    private $transformer;

    private $checkExists;

    private $property;

    private $sourceGroups;

    private $targetGroups;

    private $maxDepth;

    public function __construct(
        ReadAccessor $readAccessor,
        WriteMutator $writeMutator,
        TransformerInterface $transformer,
        string $property,
        bool $checkExists = false,
        array $sourceGroups = null,
        array $targetGroups = null,
        ?int $maxDepth = null
    ) {
        $this->readAccessor = $readAccessor;
        $this->writeMutator = $writeMutator;
        $this->transformer = $transformer;
        $this->property = $property;
        $this->checkExists = $checkExists;
        $this->sourceGroups = $sourceGroups;
        $this->targetGroups = $targetGroups;
        $this->maxDepth = $maxDepth;
    }

    public function getReadAccessor(): ReadAccessor
    {
        return $this->readAccessor;
    }

    public function getWriteMutator(): WriteMutator
    {
        return $this->writeMutator;
    }

    public function getTransformer(): TransformerInterface
    {
        return $this->transformer;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function checkExists(): bool
    {
        return $this->checkExists;
    }

    public function getSourceGroups(): ?array
    {
        return $this->sourceGroups;
    }

    public function getTargetGroups(): ?array
    {
        return $this->targetGroups;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }
}
