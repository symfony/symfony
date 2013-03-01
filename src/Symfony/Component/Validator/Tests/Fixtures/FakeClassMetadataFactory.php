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

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

class FakeClassMetadataFactory implements ClassMetadataFactoryInterface
{
    protected $metadatas = array();

    public function getClassMetadata($class)
    {
        if (!isset($this->metadatas[$class])) {
            throw new \RuntimeException('No metadata for class ' . $class);
        }

        return $this->metadatas[$class];
    }

    public function addClassMetadata(ClassMetadata $metadata)
    {
        $this->metadatas[$metadata->getClassName()] = $metadata;
    }
}
