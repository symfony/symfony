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

use Symfony\Component\Serializer\Mapping\ClassMetadata;

/**
 * Loads class metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface LoaderInterface
{
    /**
     * Load class metadata.
     *
     * @param ClassMetadata $metadata A metadata
     *
     * @return bool
     */
    public function loadClassMetadata(ClassMetadata $metadata);
}
