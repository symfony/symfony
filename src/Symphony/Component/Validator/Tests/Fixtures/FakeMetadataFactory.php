<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

use Symphony\Component\Validator\Exception\NoSuchMetadataException;
use Symphony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symphony\Component\Validator\MetadataInterface;

class FakeMetadataFactory implements MetadataFactoryInterface
{
    protected $metadatas = array();

    public function getMetadataFor($class)
    {
        $hash = null;

        if (is_object($class)) {
            $hash = spl_object_hash($class);
            $class = get_class($class);
        }

        if (!is_string($class)) {
            throw new NoSuchMetadataException(sprintf('No metadata for type %s', gettype($class)));
        }

        if (!isset($this->metadatas[$class])) {
            if (isset($this->metadatas[$hash])) {
                return $this->metadatas[$hash];
            }

            throw new NoSuchMetadataException(sprintf('No metadata for "%s"', $class));
        }

        return $this->metadatas[$class];
    }

    public function hasMetadataFor($class)
    {
        $hash = null;

        if (is_object($class)) {
            $hash = spl_object_hash($class);
            $class = get_class($class);
        }

        if (!is_string($class)) {
            return false;
        }

        return isset($this->metadatas[$class]) || isset($this->metadatas[$hash]);
    }

    public function addMetadata($metadata)
    {
        $this->metadatas[$metadata->getClassName()] = $metadata;
    }

    public function addMetadataForValue($value, MetadataInterface $metadata)
    {
        $key = is_object($value) ? spl_object_hash($value) : $value;
        $this->metadatas[$key] = $metadata;
    }
}
