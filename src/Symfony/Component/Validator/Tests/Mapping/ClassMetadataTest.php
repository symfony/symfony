<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class ClassMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';
    const PROVIDERCLASS = 'Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity';

    protected $metadata;

    protected function setUp()
    {
        $this->metadata = new ClassMetadata(self::CLASSNAME);
    }

    protected function tearDown()
    {
        $this->metadata = null;
    }

    public function testAddConstraintDoesNotAcceptValid()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->metadata->addConstraint(new Valid());
    }

    public function testAddConstraintRequiresClassConstraints()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->metadata->addConstraint(new PropertyConstraint());
    }

    public function testAddPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addPropertyConstraint('lastName', new ConstraintB());

        $this->assertEquals(array('firstName', 'lastName'), $this->metadata->getConstrainedProperties());
    }

    public function testAddMultiplePropertyConstraints()
    {
        $this->metadata->addPropertyConstraints('lastName', array(new ConstraintA(), new ConstraintB()));

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'Entity'))),
            new ConstraintB(array('groups' => array('Default', 'Entity'))),
        );

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('lastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'Entity'))),
            new ConstraintB(array('groups' => array('Default', 'Entity'))),
        );

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddMultipleGetterConstraints()
    {
        $this->metadata->addGetterConstraints('lastName', array(new ConstraintA(), new ConstraintB()));

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'Entity'))),
            new ConstraintB(array('groups' => array('Default', 'Entity'))),
        );

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testMergeConstraintsMergesClassConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addConstraint(new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addConstraint(new ConstraintA());

        $constraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParent',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'Entity',
            ))),
        );

        $this->assertEquals($constraints, $this->metadata->getConstraints());
    }

    public function testMergeConstraintsMergesMemberConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('firstName', new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $constraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParent',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'Entity',
            ))),
        );

        $members = $this->metadata->getPropertyMetadata('firstName');

        $this->assertCount(1, $members);
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($constraints, $members[0]->getConstraints());
    }

    public function testMemberMetadatas()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->assertTrue($this->metadata->hasPropertyMetadata('firstName'));
        $this->assertFalse($this->metadata->hasPropertyMetadata('non_existant_field'));
    }

    public function testMergeConstraintsKeepsPrivateMembersSeparate()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('internal', new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addPropertyConstraint('internal', new ConstraintA());

        $parentConstraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParent',
                'Entity',
            ))),
        );
        $constraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'Entity',
            ))),
        );

        $members = $this->metadata->getPropertyMetadata('internal');

        $this->assertCount(2, $members);
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($parentConstraints, $members[0]->getConstraints());
        $this->assertEquals(self::CLASSNAME, $members[1]->getClassName());
        $this->assertEquals($constraints, $members[1]->getConstraints());
    }

    public function testGetReflectionClass()
    {
        $reflClass = new \ReflectionClass(self::CLASSNAME);

        $this->assertEquals($reflClass, $this->metadata->getReflectionClass());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(array('property1' => 'A')));
        $this->metadata->addConstraint(new ConstraintB(array('groups' => 'TestGroup')));
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testGroupSequencesWorkIfContainingDefaultGroup()
    {
        $this->metadata->setGroupSequence(array('Foo', $this->metadata->getDefaultGroup()));
    }

    public function testGroupSequencesFailIfNotContainingDefaultGroup()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\GroupDefinitionException');

        $this->metadata->setGroupSequence(array('Foo', 'Bar'));
    }

    public function testGroupSequencesFailIfContainingDefault()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\GroupDefinitionException');

        $this->metadata->setGroupSequence(array('Foo', $this->metadata->getDefaultGroup(), Constraint::DEFAULT_GROUP));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\GroupDefinitionException
     */
    public function testGroupSequenceFailsIfGroupSequenceProviderIsSet()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $metadata->setGroupSequence(array('GroupSequenceProviderEntity', 'Foo'));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\GroupDefinitionException
     */
    public function testGroupSequenceProviderFailsIfGroupSequenceIsSet()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequence(array('GroupSequenceProviderEntity', 'Foo'));
        $metadata->setGroupSequenceProvider(true);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\GroupDefinitionException
     */
    public function testGroupSequenceProviderFailsIfDomainClassIsInvalid()
    {
        $metadata = new ClassMetadata('stdClass');
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProvider()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $this->assertTrue($metadata->isGroupSequenceProvider());
    }

    /**
     * https://github.com/symfony/symfony/issues/11604
     */
    public function testGetMemberMetadatasReturnsEmptyArrayWithoutConfiguredMetadata()
    {
        $this->assertCount(0, $this->metadata->getMemberMetadatas('foo'), '->getMemberMetadatas() returns an empty collection if no metadata is configured for the given property');
    }

    /**
     * https://github.com/symfony/symfony/issues/11604
     */
    public function testGetPropertyMetadataReturnsEmptyArrayWithoutConfiguredMetadata()
    {
        $this->assertCount(0, $this->metadata->getPropertyMetadata('foo'), '->getPropertyMetadata() returns an empty collection if no metadata is configured for the given property');
    }
}
