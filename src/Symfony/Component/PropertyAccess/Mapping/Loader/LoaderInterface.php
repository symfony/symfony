<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Loader;

use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

/**
 * Loads {@link ClassMetadataInterface}.
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
interface LoaderInterface
{
    /**
     * Load class metadata.
     *
     * @param ClassMetadata $classMetadata A metadata
     *
     * @return bool
     */
    public function loadClassMetadata(ClassMetadata $classMetadata);
}
