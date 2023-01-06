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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\MemberMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class MemberMetadataTest extends TestCase
{
    protected $metadata;

    protected function setUp(): void
    {
        $this->metadata = new TestMemberMetadata(
            Entity::class,
            'getLastName',
            'lastName'
        );
    }

    protected function tearDown(): void
    {
        $this->metadata = null;
    }

    public function testAddConstraintRequiresClassConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);

        $this->metadata->addConstraint(new ClassConstraint());
    }

    public function testAddCompositeConstraintRejectsNestedClassConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint "Symfony\Component\Validator\Tests\Fixtures\ClassConstraint" cannot be put on properties or getters.');

        $this->metadata->addConstraint(new PropertyCompositeConstraint([new ClassConstraint()]));
    }

    public function testAddCompositeConstraintRejectsDeepNestedClassConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint "Symfony\Component\Validator\Tests\Fixtures\ClassConstraint" cannot be put on properties or getters.');

        $this->metadata->addConstraint(new Collection(['field1' => new Required([new ClassConstraint()])]));
    }

    public function testAddCompositeConstraintAcceptsNestedPropertyConstraints()
    {
        $this->metadata->addConstraint($constraint = new PropertyCompositeConstraint([new PropertyConstraint()]));
        $this->assertSame($this->metadata->getConstraints(), [$constraint]);
    }

    public function testAddCompositeConstraintAcceptsDeepNestedPropertyConstraints()
    {
        $this->metadata->addConstraint($constraint = new Collection(['field1' => new Required([new PropertyConstraint()])]));
        $this->assertSame($this->metadata->getConstraints(), [$constraint]);
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(['property1' => 'A']));
        $this->metadata->addConstraint(new ConstraintB(['groups' => 'TestGroup']));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionCascaded()
    {
        $this->metadata->addConstraint(new Valid(['traverse' => true]));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionNotCascaded()
    {
        $this->metadata->addConstraint(new Valid(['traverse' => false]));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }
}

class TestMemberMetadata extends MemberMetadata
{
    public function getPropertyValue($object): mixed
    {
    }

    protected function newReflectionMember($object): \ReflectionMethod
    {
    }
}

class PropertyCompositeConstraint extends Composite
{
    public $nested;

    public function getDefaultOption(): ?string
    {
        return $this->getCompositeOption();
    }

    protected function getCompositeOption(): string
    {
        return 'nested';
    }
}
