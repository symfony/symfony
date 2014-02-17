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
class ValueMetadata implements MetadataInterface
{
    /**
     * Returns all constraints for a given validation group.
     *
     * @param string $group The validation group.
     *
     * @return \Symfony\Component\Validator\Constraint[] A list of constraint instances.
     */
    public function findConstraints($group)
    {

    }

    public function supportsCascading()
    {

    }

    public function supportsIteration()
    {

    }

    public function supportsRecursiveIteration()
    {

    }
}
