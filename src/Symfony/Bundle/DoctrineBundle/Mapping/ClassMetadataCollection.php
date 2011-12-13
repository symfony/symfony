<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Mapping;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassMetadataCollection
{
    private $path;
    private $namespace;
    private $metadata;

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }
}
