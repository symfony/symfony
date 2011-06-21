<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

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
