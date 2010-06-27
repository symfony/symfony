<?php

namespace Symfony\Tests\Components\Validator\Mapping;

require_once __DIR__.'/../Fixtures/ConstraintA.php';
require_once __DIR__.'/../Fixtures/ConstraintB.php';

use Symfony\Tests\Components\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Components\Validator\Fixtures\ConstraintB;
use Symfony\Components\Validator\Mapping\ElementMetadata;

class ElementMetadataTest extends \PHPUnit_Framework_TestCase
{
    protected $metadata;

    public function setUp()
    {
        $this->metadata = new ElementMetadata('Symfony\Tests\Components\Validator\Fixtures\Entity');
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
}
