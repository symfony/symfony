<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping;

use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class AttributeMetadata implements AttributeMetadataInterface
{
    private string $name;
    private array $groups = [];
    private ?int $maxDepth = null;
    private ?string $serializedName = null;
    private ?PropertyPath $serializedPath = null;
    private bool $ignore = false;

    /**
     * @var array[] Normalization contexts per group name ("*" applies to all groups)
     */
    private array $normalizationContexts = [];

    /**
     * @var array[] Denormalization contexts per group name ("*" applies to all groups)
     */
    private array $denormalizationContexts = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addGroup(string $group): void
    {
        if (!\in_array($group, $this->groups, true)) {
            $this->groups[] = $group;
        }
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setMaxDepth(?int $maxDepth): void
    {
        $this->maxDepth = $maxDepth;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    public function setSerializedName(?string $serializedName): void
    {
        $this->serializedName = $serializedName;
    }

    public function getSerializedName(): ?string
    {
        return $this->serializedName;
    }

    public function setSerializedPath(?PropertyPath $serializedPath = null): void
    {
        $this->serializedPath = $serializedPath;
    }

    public function getSerializedPath(): ?PropertyPath
    {
        return $this->serializedPath;
    }

    public function setIgnore(bool $ignore): void
    {
        $this->ignore = $ignore;
    }

    public function isIgnored(): bool
    {
        return $this->ignore;
    }

    public function getNormalizationContexts(): array
    {
        return $this->normalizationContexts;
    }

    public function getNormalizationContextForGroups(array $groups): array
    {
        $contexts = [];
        foreach ($groups as $group) {
            $contexts[] = $this->normalizationContexts[$group] ?? [];
        }

        return array_merge($this->normalizationContexts['*'] ?? [], ...$contexts);
    }

    public function setNormalizationContextForGroups(array $context, array $groups = []): void
    {
        if (!$groups) {
            $this->normalizationContexts['*'] = $context;
        }

        foreach ($groups as $group) {
            $this->normalizationContexts[$group] = $context;
        }
    }

    public function getDenormalizationContexts(): array
    {
        return $this->denormalizationContexts;
    }

    public function getDenormalizationContextForGroups(array $groups): array
    {
        $contexts = [];
        foreach ($groups as $group) {
            $contexts[] = $this->denormalizationContexts[$group] ?? [];
        }

        return array_merge($this->denormalizationContexts['*'] ?? [], ...$contexts);
    }

    public function setDenormalizationContextForGroups(array $context, array $groups = []): void
    {
        if (!$groups) {
            $this->denormalizationContexts['*'] = $context;
        }

        foreach ($groups as $group) {
            $this->denormalizationContexts[$group] = $context;
        }
    }

    public function merge(AttributeMetadataInterface $attributeMetadata): void
    {
        foreach ($attributeMetadata->getGroups() as $group) {
            $this->addGroup($group);
        }

        // Overwrite only if not defined
        $this->maxDepth ??= $attributeMetadata->getMaxDepth();
        $this->serializedName ??= $attributeMetadata->getSerializedName();
        $this->serializedPath ??= $attributeMetadata->getSerializedPath();

        // Overwrite only if both contexts are empty
        if (!$this->normalizationContexts && !$this->denormalizationContexts) {
            $this->normalizationContexts = $attributeMetadata->getNormalizationContexts();
            $this->denormalizationContexts = $attributeMetadata->getDenormalizationContexts();
        }

        if ($ignore = $attributeMetadata->isIgnored()) {
            $this->ignore = $ignore;
        }
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'groups' => $this->groups,
            'maxDepth' => $this->maxDepth,
            'serializedName' => $this->serializedName,
            'serializedPath' => $this->serializedPath,
            'ignore' => $this->ignore,
            'normalizationContexts' => $this->normalizationContexts,
            'denormalizationContexts' => $this->denormalizationContexts,
        ];
    }
}
