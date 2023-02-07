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
    public $name;

    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getGroups()} instead.
     */
    public $groups = [];

    /**
     * @var int|null
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getMaxDepth()} instead.
     */
    public $maxDepth;

    /**
     * @var array<string, string|null> An array of serialized names by group
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSerializedNames()} instead.
     */
    public $serializedName = [];

    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSerializedPath()} instead.
     */
    public ?PropertyPath $serializedPath = null;

    /**
     * @var bool
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link isIgnored()} instead.
     */
    public $ignore = false;

    /**
     * @var array[] Normalization contexts per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getNormalizationContexts()} instead.
     */
    public $normalizationContexts = [];

    /**
     * @var array[] Denormalization contexts per group name ("*" applies to all groups)
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getDenormalizationContexts()} instead.
     */
    public $denormalizationContexts = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addGroup(string $group)
    {
        if (!\in_array($group, $this->groups)) {
            $this->groups[] = $group;
        }
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function setMaxDepth(?int $maxDepth)
    {
        $this->maxDepth = $maxDepth;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    public function setSerializedNames(array $serializedNames): void
    {
        $this->serializedName = $serializedNames;
    }

    /**
     * Set a serialization name for given groups.
     *
     * @param string[] $groups
     */
    public function setSerializedName(string $serializedName = null/* , array $groups = [] */)
    {
        if (1 > \func_num_args()) {
            trigger_deprecation('symfony/serializer', '6.2', 'Calling "%s()" without any arguments is deprecated, pass null explicitly instead.', __METHOD__);
        }
        
        if (\func_num_args() < 2) {
            $groups = [];
        } else {
            $groups = func_get_arg(1);

            if (!\is_array($groups)) {
                throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be array, "%s" given.', __METHOD__, get_debug_type($groups)));
            }
        }

        foreach ($groups ?: ['*'] as $group) {
            $this->serializedName[$group] = $serializedName;
        }
    }

    public function getSerializedNames(): array
    {
        return $this->serializedName;
    }

    /**
     * Gets the serialization name for given groups.
     *
     * @param string[] $groups
     */
    public function getSerializedName(/* array $groups = [] */): ?string
    {
        if (\func_num_args() < 1) {
            $groups = [];
        } else {
            $groups = func_get_arg(0);

            if (!\is_array($groups)) {
                throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be array, "%s" given.', __METHOD__, get_debug_type($groups)));
            }
        }

        foreach ($groups as $group) {
            if (isset($this->serializedName[$group])) {
                return $this->serializedName[$group];
            }
        }

        return $this->serializedName['*'] ?? null;
    }

    public function setSerializedPath(PropertyPath $serializedPath = null): void
    {
        $this->serializedPath = $serializedPath;
    }

    public function getSerializedPath(): ?PropertyPath
    {
        return $this->serializedPath;
    }

    public function setIgnore(bool $ignore)
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

    public function merge(AttributeMetadataInterface $attributeMetadata)
    {
        foreach ($attributeMetadata->getGroups() as $group) {
            $this->addGroup($group);
        }

        // Overwrite only if not defined
        $this->maxDepth ??= $attributeMetadata->getMaxDepth();
        $this->serializedName ??= $attributeMetadata->getSerializedNames();
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

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep(): array
    {
        return ['name', 'groups', 'maxDepth', 'serializedName', 'serializedPath', 'ignore', 'normalizationContexts', 'denormalizationContexts'];
    }

    public function __wakeup()
    {
        // Preserve compatibility with existing serialized payloads
        if (null === $this->serializedName) {
            $this->serializedName = [];
        } elseif (\is_string($this->serializedName)) {
            $this->serializedName = [
                '*' => $this->serializedName,
            ];
        }
    }
}
