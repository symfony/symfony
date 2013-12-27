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

use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Mapping\ElementMetadata;
use Symfony\Component\Validator\Constraints\Group;

class ElementMetadataTest extends \PHPUnit_Framework_TestCase
{
    protected $metadata;

    protected function setUp()
    {
        $this->metadata = new TestElementMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');
    }

    protected function tearDown()
    {
        $this->metadata = null;
    }

    public function testAddConstraints()
    {
        $this->metadata->addConstraint($constraint1 = new ConstraintA());
        $this->metadata->addConstraint($constraint2 = new ConstraintA());

        $this->assertEquals(array($constraint1, $constraint2), $this->metadata->getConstraints());
    }

    public function testMultipleConstraintsOfTheSameType()
    {
        $constraint1 = new ConstraintA(array('property1' => 'A'));
        $constraint2 = new ConstraintA(array('property1' => 'B'));

        $this->metadata->addConstraint($constraint1);
        $this->metadata->addConstraint($constraint2);

        $this->assertEquals(array($constraint1, $constraint2), $this->metadata->getConstraints());
    }

    public function testAddGroupConstraint()
    {
        $constraintA = new ConstraintA();
        $constraintB = new ConstraintB(array('groups' => 'TestGroupB'));

        $constraint = new Group(array(
            'groups' => 'TestGroupA',
            'constraints' => array($constraintA, $constraintB),
        ));

        $metadata = $this->metadata->addConstraint($constraint);

        $this->assertSame($this->metadata, $metadata);
        $this->assertCount(2, $this->metadata->getConstraints());
        $this->assertSame(array($constraintA, $constraintB), $this->metadata->getConstraints());
        $this->assertSame(array($constraintA, $constraintB), $this->metadata->findConstraints('TestGroupA'));
        $this->assertSame(array($constraintB), $this->metadata->findConstraints('TestGroupB'));
    }

    public function testFindConstraintsByGroup()
    {
        $constraint1 = new ConstraintA(array('groups' => 'TestGroup'));
        $constraint2 = new ConstraintB();

        $this->metadata->addConstraint($constraint1);
        $this->metadata->addConstraint($constraint2);

        $this->assertEquals(array($constraint1), $this->metadata->findConstraints('TestGroup'));
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(array('property1' => 'A')));
        $this->metadata->addConstraint(new ConstraintB(array('groups' => 'TestGroup')));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }
}

class TestElementMetadata extends ElementMetadata {}
