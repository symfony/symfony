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
    private array $serializedNames = [];
    private array $serializedPaths = [];
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

    /**
     * @param list<string> $groups
     */
    public function setSerializedName(?string $serializedName, array $groups = ['*']): void
    {
        if (isset($serializedName)) {
            foreach ($groups as $group) {
                $this->serializedNames[$group] = $serializedName;
            }
        } else {
            foreach ($groups as $group) {
                unset($this->serializedNames[$group]);
            }
        }
    }

    /**
     * @param list<string> $groups
     */
    public function getSerializedName(array $groups = ['*']): ?string
    {
        // first match in declaration order, so that the order of the groups in the context has no effect
        foreach ($this->serializedNames as $group => $serializedName) {
            if ('*' !== $group && \in_array($group, $groups, true)) {
                return $serializedName;
            }
        }

        return $this->serializedNames['*'] ?? null;
    }

    public function getSerializedNames(): array
    {
        return $this->serializedNames;
    }

    /**
     * @param list<string> $groups
     */
    public function setSerializedPath(?PropertyPath $serializedPath = null, array $groups = ['*']): void
    {
        if (isset($serializedPath)) {
            foreach ($groups as $group) {
                $this->serializedPaths[$group] = $serializedPath;
            }
        } else {
            foreach ($groups as $group) {
                unset($this->serializedPaths[$group]);
            }
        }
    }

    /**
     * @param list<string> $groups
     */
    public function getSerializedPath(array $groups = ['*']): ?PropertyPath
    {
        // first match in declaration order, so that the order of the groups in the context has no effect
        foreach ($this->serializedPaths as $group => $serializedPath) {
            if ('*' !== $group && \in_array($group, $groups, true)) {
                return $serializedPath;
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

        // Overwrite only if serialized names are empty
        if (!$this->serializedNames) {
            $this->serializedNames = self::getSerializedNamesFromAttributeMetadata($attributeMetadata);
        }

        // Overwrite only if serialized paths are empty
        if (!$this->serializedPaths) {
            $this->serializedPaths = self::getSerializedPathsFromAttributeMetadata($attributeMetadata);
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
     * BC layer for extraction of serialized names from attribute metadata.
     * Can be removed as soon as AttributeMetadataInterface::getSerializedNames() become part of the interface.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public static function getSerializedNamesFromAttributeMetadata(AttributeMetadataInterface $attributeMetadata): array
    {
        if (method_exists($attributeMetadata, 'getSerializedNames')) {
            return $attributeMetadata->getSerializedNames();
        }

        if (null !== $serializedName = $attributeMetadata->getSerializedName()) {
            return ['*' => $serializedName];
        }

        return [];
    }

    /**
     * BC layer for extraction of serialized paths from attribute metadata.
     * Can be removed as soon as AttributeMetadataInterface::getSerializedPaths() become part of the interface.
     *
     * @internal
     *
     * @return array<string, PropertyPath>
     */
    public static function getSerializedPathsFromAttributeMetadata(AttributeMetadataInterface $attributeMetadata): array
    {
        if (method_exists($attributeMetadata, 'getSerializedPaths')) {
            return $attributeMetadata->getSerializedPaths();
        }

        if (null !== $serializedPath = $attributeMetadata->getSerializedPath()) {
            return ['*' => $serializedPath];
        }

        return [];
    }

    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'groups' => $this->groups,
            'maxDepth' => $this->maxDepth,
            'serializedNames' => $this->serializedNames,
            'serializedPaths' => $this->serializedPaths,
            'ignore' => $this->ignore,
            'normalizationContexts' => $this->normalizationContexts,
            'denormalizationContexts' => $this->denormalizationContexts,
        ];
    }

    public function __unserialize(array $data): void
    {
        // a cache warmed before the names and paths became per-group holds the single-value keys
        if (\array_key_exists('serializedName', $data)) {
            $data['serializedNames'] = null !== $data['serializedName'] ? ['*' => $data['serializedName']] : [];
            $data['serializedPaths'] = null !== ($data['serializedPath'] ?? null) ? ['*' => $data['serializedPath']] : [];
            unset($data['serializedName'], $data['serializedPath']);
        }

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }
}
