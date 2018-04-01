<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Mapping\Factory;

use Symphony\Component\Validator\Exception\NoSuchMetadataException;
use Symphony\Component\Validator\Mapping\MetadataInterface;

/**
 * Returns {@link \Symphony\Component\Validator\Mapping\MetadataInterface} instances for values.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface MetadataFactoryInterface
{
    /**
     * Returns the metadata for the given value.
     *
     * @param mixed $value Some value
     *
     * @return MetadataInterface The metadata for the value
     *
     * @throws NoSuchMetadataException If no metadata exists for the given value
     */
    public function getMetadataFor($value);

    /**
     * Returns whether the class is able to return metadata for the given value.
     *
     * @param mixed $value Some value
     *
     * @return bool Whether metadata can be returned for that value
     */
    public function hasMetadataFor($value);
}
