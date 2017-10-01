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
class AttributeMetadata implements AttributeMetadataMemberInterface
{
    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getName()} instead.
     */
    public $name;

    /**
     * @var array
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getMemberGroups()} instead.
     */
    public $memberGroups = array();

    /**
     * @var int|null
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getMemberMaxDepth()} instead.
     */
    public $memberMaxDepth = array();

    /**
     * Constructs a metadata for the given attribute.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function addGroup($group)
    {
        $this->addMemberGroup($this->name, $group);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups()
    {
        if (empty($this->memberGroups)) {
            return array();
        }

        return array_values(array_unique(array_merge(...array_values($this->memberGroups))));
    }

    /**
     * {@inheritdoc}
     */
    public function addMemberGroup($memberName, $group)
    {
        if (!isset($this->memberGroups[$memberName])) {
            $this->memberGroups[$memberName] = array();
        }

        if (!in_array($group, $this->memberGroups[$memberName])) {
            $this->memberGroups[$memberName][] = $group;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMemberGroups()
    {
        return $this->memberGroups;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupsByMemberName($memberName)
    {
        if (!isset($this->memberGroups[$memberName])) {
            return array();
        }

        return $this->memberGroups[$memberName];
    }

    /**
     * {@inheritdoc}
     */
    public function setMaxDepth($maxDepth)
    {
        $this->setMaxDepthByMemberName($this->name, $maxDepth);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxDepth()
    {
        return end($this->memberMaxDepth);
    }

    /**
     * {@inheritdoc}
     */
    public function getMemberMaxDepth()
    {
        return $this->memberMaxDepth;
    }

    /**
     * {@inheritdoc}
     */
    public function setMaxDepthByMemberName($memberName, $maxDepth)
    {
        $this->memberMaxDepth[$memberName] = $maxDepth;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxDepthByMemberName($memberName)
    {
        if (!isset($this->memberMaxDepth[$memberName])) {
            return null;
        }

        return $this->memberMaxDepth[$memberName];
    }

    /**
     * {@inheritdoc}
     */
    public function merge(AttributeMetadataInterface $attributeMetadata)
    {
        if (!$attributeMetadata instanceof AttributeMetadataMemberInterface) {
            throw new \LogicException('Can only merge instances of AttributeMetadataMemberInterface');
        }
        foreach ($attributeMetadata->getMemberGroups() as $memberName => $groups) {
            foreach ($groups as $group) {
                $this->addMemberGroup($memberName, $group);
            }
        }

        // Overwrite only if not defined
        if (array() === $this->memberMaxDepth) {
            foreach ($attributeMetadata->getMemberMaxDepth() as $memberName => $maxDepth) {
                $this->setMaxDepthByMemberName($memberName, $maxDepth);
            }
        }
    }

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep()
    {
        return array('name', 'memberGroups', 'memberMaxDepth');
    }
}
