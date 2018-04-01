<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Mapping\Loader;

use Symphony\Component\Validator\Mapping\ClassMetadata;

/**
 * Loads validation metadata into {@link ClassMetadata} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface LoaderInterface
{
    /**
     * Loads validation metadata into a {@link ClassMetadata} instance.
     *
     * @return bool Whether the loader succeeded
     */
    public function loadClassMetadata(ClassMetadata $metadata);
}
