<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Mapping;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Tests\Component\Validator\Fixtures\Entity;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintB;
use Symfony\Tests\Component\Validator\Fixtures\PropertyConstraint;

require_once __DIR__.'/../Fixtures/Entity.php';
require_once __DIR__.'/../Fixtures/ConstraintA.php';
require_once __DIR__.'/../Fixtures/ConstraintB.php';
require_once __DIR__.'/../Fixtures/PropertyConstraint.php';

class ClassMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Tests\Component\Validator\Fixtures\EntityParent';

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

        $members = $this->metadata->getMemberMetadatas('firstName');

        $this->assertEquals(1, count($members));
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($constraints, $members[0]->getConstraints());
    }

    public function testMemberMetadatas()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->assertTrue($this->metadata->hasMemberMetadatas('firstName'));
        $this->assertFalse($this->metadata->hasMemberMetadatas('non_existant_field'));
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

        $members = $this->metadata->getMemberMetadatas('internal');

        $this->assertEquals(2, count($members));
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
}

