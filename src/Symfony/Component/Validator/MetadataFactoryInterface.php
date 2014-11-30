<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Returns {@link MetadataInterface} instances for values.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface MetadataFactoryInterface
{
    /**
     * Returns the metadata for the given value.
     *
     * @param mixed $value Some value.
     *
     * @return MetadataInterface The metadata for the value.
     *
     * @throws Exception\NoSuchMetadataException If no metadata exists for the value.
     */
    public function getMetadataFor($value);

    /**
     * Returns whether metadata exists for the given value.
     *
     * @param mixed $value Some value.
     *
     * @return bool Whether metadata exists for the value.
     */
    public function hasMetadataFor($value);
}
