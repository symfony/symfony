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
