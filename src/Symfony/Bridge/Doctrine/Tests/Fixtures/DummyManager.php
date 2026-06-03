<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

class DummyManager implements ObjectManager
{
    public $bar;

    public function __construct()
    {
    }

    public function find($className, $id): ?object
    {
    }

    public function persist($object): void
    {
    }

    public function remove($object): void
    {
    }

    public function merge($object)
    {
    }

    public function clear($objectName = null): void
    {
    }

    public function detach($object): void
    {
    }

    public function refresh($object): void
    {
    }

    public function flush(): void
    {
    }

    public function getRepository($className): ObjectRepository
    {
    }

    public function getClassMetadata($className): ClassMetadata
    {
    }

    public function getMetadataFactory(): ClassMetadataFactory
    {
    }

    public function initializeObject($obj): void
    {
    }

    public function contains($object): bool
    {
    }

    public function isUninitializedObject($value): bool
    {
    }
}
