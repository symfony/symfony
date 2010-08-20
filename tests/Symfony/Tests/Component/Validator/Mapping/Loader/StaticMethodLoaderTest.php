<?php

namespace Symfony\Tests\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;

class StaticMethodLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderEntity');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderEntity');

        $loader->loadClassMetadata($metadata);

        $this->assertEquals(StaticLoaderEntity::$invokedWith, $metadata);
    }
}

class StaticLoaderEntity
{
    static public $invokedWith = null;

    public static function loadMetadata(ClassMetadata $metadata)
    {
        self::$invokedWith = $metadata;
    }
}
