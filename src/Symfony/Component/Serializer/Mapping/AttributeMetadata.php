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

/**
 * {@inheritdoc}
 *
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
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSerializedNames()} instead.
     */
    public $serializedNames = [];

    /**
     * @var bool
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link isIgnored()} instead.
     */
    public $ignore = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function addGroup(string $group)
    {
        if (!\in_array($group, $this->groups)) {
            $this->groups[] = $group;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * {@inheritdoc}
     */
    public function setMaxDepth(?int $maxDepth)
    {
        $this->maxDepth = $maxDepth;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxDepth()
    {
        return $this->maxDepth;
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializedName(string $serializedName = null)
    {
        $this->addSerializedName($serializedName);
    }

    /**
     * {@inheritdoc}
     */
    public function addSerializedName(string $serializedName, array $groups = [])
    {
        $this->serializedNames[$serializedName] = $groups;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializedName(): ?string
    {
        return $this->getSerializedNameForGroups();
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializedNames(): array
    {
        return $this->serializedNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializedNameForGroups(array $groups = []): ?string
    {
        $defaultSerializedName = null;

        foreach ($this->serializedNames as $serializedName => $groupsForSerializedName) {
            if (!$groupsForSerializedName) {
                $defaultSerializedName = $serializedName;
            }

            if (array_intersect($groups, $groupsForSerializedName)) {
                return $serializedName;
            }
        }

        return $defaultSerializedName;
    }

    /**
     * {@inheritdoc}
     */
    public function setIgnore(bool $ignore)
    {
        $this->ignore = $ignore;
    }

    /**
     * {@inheritdoc}
     */
    public function isIgnored(): bool
    {
        return $this->ignore;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(AttributeMetadataInterface $attributeMetadata)
    {
        foreach ($attributeMetadata->getGroups() as $group) {
            $this->addGroup($group);
        }

        // Overwrite only if not defined
        if (null === $this->maxDepth) {
            $this->maxDepth = $attributeMetadata->getMaxDepth();
        }

        // Overwrite only if empty or nullable array
        if (!$this->serializedNames) {
            $this->serializedNames = $attributeMetadata->getSerializedNames();
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
    public function __sleep()
    {
        return ['name', 'groups', 'maxDepth', 'serializedNames', 'ignore'];
    }
}
