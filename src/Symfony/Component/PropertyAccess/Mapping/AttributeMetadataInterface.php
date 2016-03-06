<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping;

/**
 * Stores metadata needed for overriding attributes access methods.
 *
 * @internal
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
interface AttributeMetadataInterface
{
    /**
     * Gets the attribute name.
     *
     * @return string
     */
    public function getName();

    /**
     * Gets the setter method name.
     *
     * @return string
     */
    public function getSetter();

    /**
     * Sets the setter method name.
     */
    public function setSetter($setter);

    /**
     * Gets the getter method name.
     *
     * @return string
     */
    public function getGetter();

    /**
     * Sets the getter method name.
     */
    public function setGetter($getter);

    /**
     * Gets the adder method name.
     *
     * @return string
     */
    public function getAdder();

    /**
     * Sets the adder method name.
     */
    public function setAdder($adder);

    /**
     * Gets the remover method name.
     *
     * @return string
     */
    public function getRemover();

    /**
     * Sets the remover method name.
     */
    public function setRemover($remover);

    /**
     * Merges an {@see AttributeMetadataInterface} with in the current one.
     *
     * @param AttributeMetadataInterface $attributeMetadata
     */
    public function merge(AttributeMetadataInterface $attributeMetadata);
}
