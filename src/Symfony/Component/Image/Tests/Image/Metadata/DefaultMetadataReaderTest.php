<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Metadata;

use Symfony\Component\Image\Image\Metadata\DefaultMetadataReader;

class DefaultMetadataReaderTest extends MetadataReaderTestCase
{
    protected function getReader()
    {
        return new DefaultMetadataReader();
    }
}
