<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Metadata;

use Symfony\Component\Image\Exception\InvalidArgumentException;

interface MetadataReaderInterface
{
    /**
     * Reads metadata from a file.
     *
     * @param $file the path to the file where to read metadata
     *
     * @throws InvalidArgumentException in case the file does not exist
     *
     * @return MetadataBag
     */
    public function readFile($file);

    /**
     * Reads metadata from a binary string.
     *
     * @param $data the binary string to read
     * @param $originalResource an optional resource to gather stream metadata
     *
     * @return MetadataBag
     */
    public function readData($data, $originalResource = null);

    /**
     * Reads metadata from a stream.
     *
     * @param $resource the stream to read
     *
     * @throws InvalidArgumentException in case the resource is not valid
     *
     * @return MetadataBag
     */
    public function readStream($resource);
}
