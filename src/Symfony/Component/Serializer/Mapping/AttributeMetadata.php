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
 */
class AttributeMetadata implements AttributeMetadataInterface
{
    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getName()} instead.
     */
    public string $name;

    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getGroups()} instead.
     */
    public array $groups = [];

    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getMaxDepth()} instead.
     */
    public ?int $maxDepth = null;

    /**
     * @var array<string, string|null> Serialized names per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSerializedNames()} instead.
     */
    public array $serializedNames = [];

    /**
     * @var array<string, PropertyPath|null> Serialized paths per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSerializedPaths()} instead.
     */
    public array $serializedPaths = [];

    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link isIgnored()} instead.
     */
    public bool $ignore = false;

    /**
     * @var array[] Normalization contexts per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getNormalizationContexts()} instead.
     */
    public array $normalizationContexts = [];

    /**
     * @var array[] Denormalization contexts per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getDenormalizationContexts()} instead.
     */
    public array $denormalizationContexts = [];

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

    public function setSerializedName(?string $serializedName /* , array $groups = ['*'] */): void
    {
        $groups = 2 <= \func_num_args() ? func_get_arg(1) : ['*'];

        if (!$groups) {
            $this->serializedNames['*'] = $serializedName;
        }

        foreach ($groups as $group) {
            $this->serializedNames[$group] = $serializedName;
        }
    }

    public function getSerializedName(/* array $groups = ['*'] */): ?string
    {
        $groups = 1 <= \func_num_args() ? func_get_arg(0) : ['*'];

        foreach ($groups as $group) {
            if (isset($this->serializedNames[$group])) {
                return $this->serializedNames[$group];
            }
        }

        return $this->serializedNames['*'] ?? null;
    }

    public function getSerializedNames(): array
    {
        return $this->serializedNames;
    }

    public function setSerializedPath(?PropertyPath $serializedPath = null /* , array $groups = ['*'] */): void
    {
        $groups = 2 <= \func_num_args() ? func_get_arg(1) : ['*'];

        if (!$groups) {
            $this->serializedPaths['*'] = $serializedPath;
        }

        foreach ($groups as $group) {
            $this->serializedPaths[$group] = $serializedPath;
        }
    }

    public function getSerializedPath(/* array $groups = ['*'] */): ?PropertyPath
    {
        $groups = 1 <= \func_num_args() ? func_get_arg(0) : ['*'];

        foreach ($groups as $group) {
            if (isset($this->serializedPaths[$group])) {
                return $this->serializedPaths[$group];
            }
        }

        return $this->serializedPaths['*'] ?? null;
    }

    public function getSerializedPaths(): array
    {
        return $this->serializedPaths;
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

        // Overwrite only if both serialized names and paths are empty
        if (!$this->serializedNames && !$this->serializedPaths) {
            $this->serializedNames = method_exists($attributeMetadata, 'getSerializedNames')
                ? $attributeMetadata->getSerializedNames()
                : array_filter(['*' => $attributeMetadata->getSerializedName()]);
            $this->serializedPaths = method_exists($attributeMetadata, 'getSerializedPaths')
                ? $attributeMetadata->getSerializedPaths()
                : array_filter(['*' => $attributeMetadata->getSerializedPath()]);
        }

        // Overwrite only if both contexts are empty
        if (!$this->normalizationContexts && !$this->denormalizationContexts) {
            $this->normalizationContexts = $attributeMetadata->getNormalizationContexts();
            $this->denormalizationContexts = $attributeMetadata->getDenormalizationContexts();
        }

        if ($ignore = $attributeMetadata->isIgnored()) {
            $this->ignore = $ignore;
        }
    }

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep(): array
    {
        return ['name', 'groups', 'maxDepth', 'serializedNames', 'serializedPaths', 'ignore', 'normalizationContexts', 'denormalizationContexts'];
    }
}
