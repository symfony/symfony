<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\MetadataInterface as LegacyMetadataInterface;

/**
 * A container for validation metadata.
 *
 * Most importantly, the metadata stores the constraints against which an object
 * and its properties should be validated.
 *
 * Additionally, the metadata stores whether objects should be validated
 * against their class' metadata and whether traversable objects should be
 * traversed or not.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see CascadingStrategy
 * @see TraversalStrategy
 */
interface MetadataInterface extends LegacyMetadataInterface
{
    /**
     * Returns the strategy for cascading objects.
     *
     * @return int     The cascading strategy
     *
     * @see CascadingStrategy
     */
    public function getCascadingStrategy();

    /**
     * Returns the strategy for traversing traversable objects.
     *
     * @return int     The traversal strategy
     *
     * @see TraversalStrategy
     */
    public function getTraversalStrategy();
}
