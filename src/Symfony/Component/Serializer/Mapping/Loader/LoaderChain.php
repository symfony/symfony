<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Calls multiple {@link LoaderInterface} instances in a chain.
 *
 * This class accepts multiple instances of LoaderInterface to be passed to the
 * constructor. When {@link loadClassMetadata()} is called, the same method is called
 * in all of these loaders, regardless of whether any of them was successful or not.
 * Loaders that declared which classes they map are skipped for the other classes.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class LoaderChain implements LoaderInterface
{
    /**
     * Accepts a list of LoaderInterface instances.
     *
     * @param LoaderInterface[]                            $loaders       An array of LoaderInterface instances
     * @param array<array-key, array<class-string, mixed>> $mappedClasses Maps keys of $loaders to the classes they map, given as keys
     *
     * @throws MappingException If any of the loaders does not implement LoaderInterface
     */
    public function __construct(
        private readonly array $loaders,
        private readonly array $mappedClasses = [],
    ) {
        foreach ($loaders as $loader) {
            if (!$loader instanceof LoaderInterface) {
                throw new MappingException(\sprintf('Class "%s" is expected to implement LoaderInterface.', get_debug_type($loader)));
            }
        }

        foreach ($mappedClasses as $key => $classes) {
            if (!isset($loaders[$key])) {
                throw new MappingException(\sprintf('Mapped classes are declared for loader "%s", which is not part of the chain.', $key));
            }
        }
    }

    public function loadClassMetadata(ClassMetadataInterface $metadata): bool
    {
        $loaders = $this->loaders;
        $class = $metadata->getName();

        foreach ($this->mappedClasses as $key => $classes) {
            if (!isset($classes[$class])) {
                unset($loaders[$key]);
            }
        }

        $success = false;

        foreach ($loaders as $loader) {
            if ($loader instanceof LoaderChainAwareInterface) {
                $loader->prepareLoading($metadata);
            }
        }

        foreach ($loaders as $loader) {
            $success = $loader->loadClassMetadata($metadata) || $success;
        }

        foreach ($loaders as $loader) {
            if ($loader instanceof LoaderChainAwareInterface) {
                $loader->finalizeLoading($metadata);
            }
        }

        return $success;
    }

    /**
     * @return LoaderInterface[]
     */
    public function getLoaders(): array
    {
        return $this->loaders;
    }
}
