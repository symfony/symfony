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

/**
 * Default metadata reader
 */
class DefaultMetadataReader extends AbstractMetadataReader
{
    /**
     * {@inheritdoc}
     */
    protected function extractFromFile($file)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromData($data)
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    protected function extractFromStream($resource)
    {
        return array();
    }
}
