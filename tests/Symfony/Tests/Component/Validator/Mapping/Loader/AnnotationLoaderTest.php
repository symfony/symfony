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

require_once __DIR__.'/../../Fixtures/Entity.php';
require_once __DIR__.'/../../Fixtures/ConstraintA.php';

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;

class AnnotationLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\Common\Annotations\AnnotationReader')) {
            $this->markTestSkipped('Unmet dependency: Annotations is required for this test');
        }
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $reader = new AnnotationReader();
        $loader = new AnnotationLoader($reader);
        $metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $expected->setGroupSequence(array('Foo', 'Entity'));
        $expected->addConstraint(new ConstraintA());
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Min(3));
        $expected->addPropertyConstraint('firstName', new All(array(new NotNull(), new Min(3))));
        $expected->addPropertyConstraint('firstName', new All(array('constraints' => array(new NotNull(), new Min(3)))));
        $expected->addPropertyConstraint('firstName', new Collection(array('fields' => array(
            'foo' => array(new NotNull(), new Min(3)),
            'bar' => new Min(5),
        ))));
        $expected->addPropertyConstraint('firstName', new Choice(array(
            'message' => 'Must be one of %choices%',
            'choices' => array('A', 'B'),
        )));
        $expected->addGetterConstraint('lastName', new NotNull());

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    /**
     * Test MetaData merge with parent annotation.
     */
    public function testLoadParentClassMetadata()
    {
        $loader = new AnnotationLoader(new AnnotationReader());

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\EntityParent');
        $loader->loadClassMetadata($parent_metadata);

        $expected_parent = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\EntityParent');
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $this->assertEquals($expected_parent, $parent_metadata);
    }
    /**
     * Test MetaData merge with parent annotation.
     */
    public function testLoadClassMetadataAndMerge()
    {
        $loader = new AnnotationLoader(new AnnotationReader());

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\EntityParent');
        $loader->loadClassMetadata($parent_metadata);

        $metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');

        // Merge parent metaData.
        $metadata->mergeConstraints($parent_metadata);

        $loader->loadClassMetadata($metadata);

        $expected_parent = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\EntityParent');
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $expected = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $expected->mergeConstraints($expected_parent);

        $expected->setGroupSequence(array('Foo', 'Entity'));
        $expected->addConstraint(new ConstraintA());
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Min(3));
        $expected->addPropertyConstraint('firstName', new All(array(new NotNull(), new Min(3))));
        $expected->addPropertyConstraint('firstName', new All(array('constraints' => array(new NotNull(), new Min(3)))));
        $expected->addPropertyConstraint('firstName', new Collection(array('fields' => array(
            'foo' => array(new NotNull(), new Min(3)),
            'bar' => new Min(5),
        ))));
        $expected->addPropertyConstraint('firstName', new Choice(array(
            'message' => 'Must be one of %choices%',
            'choices' => array('A', 'B'),
        )));
        $expected->addGetterConstraint('lastName', new NotNull());

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

}
