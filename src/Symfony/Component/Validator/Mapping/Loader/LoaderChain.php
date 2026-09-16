<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Loads validation metadata from multiple {@link LoaderInterface} instances.
 *
 * Pass the loaders when constructing the chain. Once
 * {@link loadClassMetadata()} is called, that method will be called on all
 * loaders in the chain. Loaders that declared which classes they map are
 * skipped for the other classes.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LoaderChain implements LoaderInterface
{
    /**
     * @param LoaderInterface[]                            $loaders       The metadata loaders to use
     * @param array<array-key, array<class-string, mixed>> $mappedClasses Maps keys of $loaders to the classes they map, given as keys
     *
     * @throws MappingException If any of the loaders has an invalid type
     */
    public function __construct(
        protected array $loaders,
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

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $loaders = $this->loaders;
        $class = $metadata->getClassName();

        foreach ($this->mappedClasses as $key => $classes) {
            if (!isset($classes[$class])) {
                unset($loaders[$key]);
            }
        }

        $success = false;

        foreach ($loaders as $loader) {
            $success = $loader->loadClassMetadata($metadata) || $success;
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
