<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Extractor;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Extractor\GroupPropertyListExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;

class GroupPropertyListExtractorTest extends TestCase
{
    public function testNoGroups()
    {
        $extractor = new ReflectionExtractor();
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $groupExtractor = new GroupPropertyListExtractor($factory, $extractor);

        $properties = $groupExtractor->getProperties(DummyNoGroups::class);

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $groupExtractor->getProperties(DummyNoGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['dummy']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertEmpty($properties);
    }

    public function testGroups()
    {
        $extractor = new ReflectionExtractor();
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $groupExtractor = new GroupPropertyListExtractor($factory, $extractor);

        $properties = $groupExtractor->getProperties(DummyGroups::class);

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $groupExtractor->getProperties(DummyGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['dummy']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertEmpty($properties);

        $properties = $groupExtractor->getProperties(DummyGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['foo']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertNotContains('bar', $properties);
        $this->assertNotContains('baz', $properties);

        $properties = $groupExtractor->getProperties(DummyGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['foo', 'bar']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $groupExtractor->getProperties(DummyGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['bar']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertNotContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $properties = $groupExtractor->getProperties(DummyGroups::class, [
            GroupPropertyListExtractor::GROUPS => ['baz']
        ]);

        $this->assertInternalType('array', $properties);
        $this->assertNotContains('foo', $properties);
        $this->assertNotContains('bar', $properties);
        $this->assertContains('baz', $properties);
    }

    public function testNullProperties()
    {
        $extractor = $this
            ->getMockBuilder(PropertyListExtractorInterface::class)
            ->getMock()
        ;
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $extractor->method('getProperties')->willReturn(null);

        $ignored = new GroupPropertyListExtractor($factory, $extractor);
        $properties = $ignored->getProperties(DummyNoGroups::class);

        $this->assertNull($properties);
    }

    public function testNoExtractor()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $ignored = new GroupPropertyListExtractor($factory);
        $properties = $ignored->getProperties(DummyNoGroups::class);
        
        $this->assertInternalType('array', $properties);
        $this->assertContains('foo', $properties);
        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);
    }
}

class DummyNoGroups
{
    public $foo;

    public $bar;

    public $baz;
}

class DummyGroups
{
    /**
     * @Groups("foo")
     */
    public $foo;

    /**
     * @Groups({"bar"})
     */
    public $bar;

    /**
     * @Groups({"bar", "baz"})
     */
    public $baz;
}
