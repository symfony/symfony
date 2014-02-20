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

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionMetadata implements MetadataInterface
{
    private $traversalStrategy;

    public function __construct($traversalStrategy)
    {
        $this->traversalStrategy = $traversalStrategy;
    }

    /**
     * Returns all constraints for a given validation group.
     *
     * @param string $group The validation group.
     *
     * @return \Symfony\Component\Validator\Constraint[] A list of constraint instances.
     */
    public function findConstraints($group)
    {
        return array();
    }

    public function getCascadingStrategy()
    {
        return CascadingStrategy::NONE;
    }

    public function getTraversalStrategy()
    {
        return $this->traversalStrategy;
    }
}
