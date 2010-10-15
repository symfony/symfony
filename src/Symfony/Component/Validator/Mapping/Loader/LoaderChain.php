<?php

namespace Symfony\Component\Validator\Mapping\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Calls multiple LoaderInterface instances in a chain
 *
 * This class accepts multiple instances of LoaderInterface to be passed to the
 * constructor. When loadClassMetadata() is called, the same method is called
 * in <em>all</em> of these loaders, regardless of whether any of them was
 * successful or not.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class LoaderChain implements LoaderInterface
{
    protected $loaders;

    /**
     * Accepts a list of LoaderInterface instances
     *
     * @param array $loaders An array of LoaderInterface instances
     *
     * @throws MappingException If any of the loaders does not implement LoaderInterface
     */
    public function __construct(array $loaders)
    {
        foreach ($loaders as $loader) {
            if (!$loader instanceof LoaderInterface) {
                throw new MappingException(sprintf('Class %s is expected to implement LoaderInterface', get_class($loader)));
            }
        }

        $this->loaders = $loaders;
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $success = false;

        foreach ($this->loaders as $loader) {
            $success = $loader->loadClassMetadata($metadata) || $success;
        }

        return $success;
    }
}