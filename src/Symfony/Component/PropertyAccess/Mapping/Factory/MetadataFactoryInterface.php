<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Factory;

use Symfony\Component\PropertyAccess\Mapping\PropertyMetadata;
use Symfony\Component\PropertyAccess\Exception;

/**
 * Returns {@link \Symfony\Component\PropertyAccess\Mapping\MetadataInterface} instances for values.
 *
 * @since  3.1
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
interface MetadataFactoryInterface
{
    /**
     * Returns the metadata for the given value.
     *
     * @param mixed $value Some value
     *
     * @return PropertyMetadata The metadata for the value
     *
     * @throws Exception\NoSuchPropertyException If no metadata exists for the given value
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
