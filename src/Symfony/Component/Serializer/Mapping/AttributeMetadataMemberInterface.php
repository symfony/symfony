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
 * Stores metadata needed for serializing and deserializing attributes.
 *
 * Primarily, the metadata stores serialization groups.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface AttributeMetadataMemberInterface extends AttributeMetadataInterface
{

    /**
     * Adds this attribute to the given group.
     *
     * @param string $membername
     * @param string $group
     */
    public function addMemberGroup($memberName, $group);

    /**
     * Gets groups of this attribute.
     *
     * @return array
     */
    public function getMemberGroups();

    /**
     * Sets the serialization max depth for this attribute.
     *
     * @param string $memberName
     * @param int|null $maxDepth
     */
    public function setMaxDepthByMemberName($memberName, $maxDepth);

    /**
     * Gets the serialization max depth for this attribute.
     *
     * @return array
     */
    public function getMemberMaxDepth();

    /**
     * Gets the serialization max depth for this attribute.
     *
     * @param string $memberName
     *
     * @return int|null
     */
    public function getMaxDepthByMemberName($memberName);
}
