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
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\MemberMetadata;

class MemberMetadataTest extends \PHPUnit_Framework_TestCase
{
    protected $metadata;

    protected function setUp()
    {
        $this->metadata = new TestMemberMetadata(
            'Symfony\Component\Validator\Tests\Fixtures\Entity',
            'getLastName',
            'lastName'
        );
    }

    protected function tearDown()
    {
        $this->metadata = null;
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

    public function testAddConstraintRequiresClassConstraints()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->metadata->addConstraint(new ClassConstraint());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(array('property1' => 'A')));
        $this->metadata->addConstraint(new ConstraintB(array('groups' => 'TestGroup')));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionCascaded()
    {
        $this->metadata->addConstraint(new Valid(array('traverse' => true, 'deep' => false)));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionCascadedDeeply()
    {
        $this->metadata->addConstraint(new Valid(array('traverse' => true, 'deep' => true)));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionNotCascaded()
    {
        $this->metadata->addConstraint(new Valid(array('traverse' => false)));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }
}

class TestMemberMetadata extends MemberMetadata
{
    public function getPropertyValue($object)
    {
    }

    protected function newReflectionMember($object)
    {
    }
}
