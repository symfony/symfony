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
use Symfony\Component\Image\Exception\NotSupportedException;

/**
 * Metadata driven by Exif information.
 */
class ExifMetadataReader extends AbstractMetadataReader
{
    public function __construct()
    {
        if (!self::isSupported()) {
            throw new NotSupportedException('PHP exif extension is required to use the ExifMetadataReader');
        }
    }

    public static function isSupported()
    {
        return function_exists('exif_read_data');
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromFile($file)
    {
        if (stream_is_local($file)) {
            if (false === is_readable($file)) {
                throw new InvalidArgumentException(sprintf('File %s is not readable.', $file));
            }

            return $this->extract($file);
        }

        if (false === $data = @file_get_contents($file)) {
            throw new InvalidArgumentException(sprintf('File %s is not readable.', $file));
        }

        return $this->doReadData($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromData($data)
    {
        return $this->doReadData($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromStream($resource)
    {
        return $this->doReadData(stream_get_contents($resource));
    }

    /**
     * Extracts metadata from raw data, merges with existing metadata.
     *
     * @param string $data
     *
     * @return MetadataBag
     */
    private function doReadData($data)
    {
        if (substr($data, 0, 2) === 'II') {
            $mime = 'image/tiff';
        } else {
            $mime = 'image/jpeg';
        }

        return $this->extract('data://'.$mime.';base64,'.base64_encode($data));
    }

    /**
     * Performs the exif data extraction given a path or data-URI representation.
     *
     * @param string $path the path to the file or the data-URI representation
     *
     * @return MetadataBag
     */
    private function extract($path)
    {
        if (false === $exifData = @exif_read_data($path, null, true, false)) {
            return array();
        }

        $metadata = array();
        $sources = array('EXIF' => 'exif', 'IFD0' => 'ifd0');

        foreach ($sources as $name => $prefix) {
            if (!isset($exifData[$name])) {
                continue;
            }
            foreach ($exifData[$name] as $prop => $value) {
                $metadata[$prefix.'.'.$prop] = $value;
            }
        }

        return $metadata;
    }
}
