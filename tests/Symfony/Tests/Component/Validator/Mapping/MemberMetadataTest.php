<?php

namespace Symfony\Tests\Component\Validator\Mapping;

require_once __DIR__.'/../Fixtures/ConstraintA.php';
require_once __DIR__.'/../Fixtures/ConstraintB.php';

use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintB;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\MemberMetadata;

class MemberMetadataTest extends \PHPUnit_Framework_TestCase
{
    protected $metadata;

    protected function setUp()
    {
        $this->metadata = new TestMemberMetadata(
            'Symfony\Tests\Component\Validator\Fixtures\Entity',
            'getLastName',
            'lastName'
        );
    }

    public function testAddValidSetsMemberToCascaded()
    {
        $result = $this->metadata->addConstraint(new Valid());

        $this->assertEquals(array(), $this->metadata->getConstraints());
        $this->assertEquals($result, $this->metadata);
        $this->assertTrue($this->metadata->isCascaded());
    }

    public function testAddOtherConstraintDoesNotSetMemberToCascaded()
    {
        $result = $this->metadata->addConstraint($constraint = new ConstraintA());

        $this->assertEquals(array($constraint), $this->metadata->getConstraints());
        $this->assertEquals($result, $this->metadata);
        $this->assertFalse($this->metadata->isCascaded());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(array('property1' => 'A')));
        $this->metadata->addConstraint(new ConstraintB(array('groups' => 'TestGroup')));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }
}

class TestMemberMetadata extends MemberMetadata
{
    public function getValue($object)
    {
    }

    protected function newReflectionMember()
    {
    }
}
