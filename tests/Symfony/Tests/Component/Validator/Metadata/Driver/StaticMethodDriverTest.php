<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Mapping\Loader;

require_once __DIR__.'/../../Fixtures/ConstraintA.php';
require_once __DIR__.'/../../Fixtures/ConstraintB.php';

use Symfony\Component\Validator\Metadata\ClassMetadata;
use Symfony\Component\Validator\Metadata\Driver\StaticMethodDriver;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintB;

class StaticMethodDriverTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->driver = new StaticMethodDriver('loadMetadata');
    }

    public function testLoadClassMetadataReturnsMetadataIfSuccessful()
    {
        $metadata = $this->driver->loadMetadataForClass(new \ReflectionClass(__NAMESPACE__ . '\StaticLoaderEntity'));
        $this->assertInstanceOf('Symfony\Component\Validator\Metadata\ClassMetadata', $metadata);
    }

    public function testLoadClassMetadataReturnsNullIfNotSuccessful()
    {
        $metadata = $this->driver->loadMetadataForClass(new \ReflectionClass('stdClass'));
        $this->assertInternalType('null', $metadata);
    }

    public function testLoadClassMetadataDoesNotCallParent()
    {
        $metadata = $this->driver->loadMetadataForClass(new \ReflectionClass(__NAMESPACE__ . '\BaseStaticLoaderDocument'));
        $this->assertSame(1, count($metadata->getConstraints()));

        $metadata = $this->driver->loadMetadataForClass(new \ReflectionClass(__NAMESPACE__ . '\StaticLoaderDocument'));
        $this->assertSame(0, count($metadata->getConstraints()));
    }
}

class StaticLoaderEntity
{
    public static function loadMetadata(ClassMetadata $metadata)
    {
    }
}

class StaticLoaderDocument extends BaseStaticLoaderDocument
{
    static public function loadMetadata(ClassMetadata $metadata)
    {
    }
}

class BaseStaticLoaderDocument
{
    static public function loadMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}
