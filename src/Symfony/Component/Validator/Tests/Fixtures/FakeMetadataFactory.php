<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;

class FakeMetadataFactory implements MetadataFactoryInterface
{
    protected $metadatas = [];

    public function getMetadataFor($class): MetadataInterface
    {
        $objectId = null;

        if (\is_object($class)) {
            $objectId = spl_object_id($class);
            $class = $class::class;
        }

        if (!\is_string($class)) {
            throw new NoSuchMetadataException(sprintf('No metadata for type "%s".', get_debug_type($class)));
        }

        if (!isset($this->metadatas[$class])) {
            if (isset($this->metadatas[$objectId])) {
                return $this->metadatas[$objectId];
            }

            throw new NoSuchMetadataException(sprintf('No metadata for "%s"', $class));
        }

        return $this->metadatas[$class];
    }

    public function hasMetadataFor($class): bool
    {
        $objectId = null;

        if (\is_object($class)) {
            $objectId = spl_object_id($class);
            $class = $class::class;
        }

        if (!\is_string($class)) {
            return false;
        }

        return isset($this->metadatas[$class]) || isset($this->metadatas[$objectId]);
    }

    public function addMetadata($metadata)
    {
        $this->metadatas[$metadata->getClassName()] = $metadata;
    }

    public function addMetadataForValue($value, MetadataInterface $metadata)
    {
        $key = \is_object($value) ? spl_object_id($value) : $value;
        $this->metadatas[$key] = $metadata;
    }
}
