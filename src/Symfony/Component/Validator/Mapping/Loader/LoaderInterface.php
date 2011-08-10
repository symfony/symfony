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

use Symfony\Component\Validator\Mapping\ClassMetadata;

interface LoaderInterface
{
    /**
     * Load a Class Metadata.
     *
     * @param ClassMetadata $metadata A metadata
     *
     * @return Boolean
     */
    function loadClassMetadata(ClassMetadata $metadata);
}
