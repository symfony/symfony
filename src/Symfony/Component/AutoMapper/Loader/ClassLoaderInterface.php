<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Loader;

use Symfony\Component\AutoMapper\MapperGeneratorMetadataInterface;

/**
 * Loads (require) a mapping given metadata.
 */
interface ClassLoaderInterface
{
    /**
     * @param MapperGeneratorMetadataInterface $mapperMetadata
     */
    public function loadClass(MapperGeneratorMetadataInterface $mapperMetadata): void;
}
