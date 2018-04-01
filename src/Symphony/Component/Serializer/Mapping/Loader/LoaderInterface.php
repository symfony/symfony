<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Mapping\Loader;

use Symphony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Loads {@link ClassMetadataInterface}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface LoaderInterface
{
    /**
     * @return bool
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata);
}
