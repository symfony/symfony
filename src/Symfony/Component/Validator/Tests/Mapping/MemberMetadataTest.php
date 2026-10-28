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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\MemberMetadata;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class MemberMetadataTest extends TestCase
{
    protected MemberMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new TestMemberMetadata(
            Entity::class,
            'getLastName',
            'lastName'
        );
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

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyConstraintInStarGroupIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/validator 8.2: Using "*" as a validation group name in the "Symfony\\Component\\Validator\\Tests\\Fixtures\\ConstraintB" constraint is deprecated. This name is reserved for future use.');

        $this->metadata->addConstraint(new ConstraintB(null, ['Group 1', '*']));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyValidInStarGroupIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/validator 8.2: Using "*" as a validation group name in the "Symfony\\Component\\Validator\\Constraints\\Valid" constraint is deprecated. This name is reserved for future use.');

        $this->metadata->addConstraint(new Valid(groups: ['*']));
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA('A'));
        $this->metadata->addConstraint(new ConstraintB(null, ['TestGroup']));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionCascaded()
    {
        $this->metadata->addConstraint(new Valid(traverse: true));

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testSerializeCollectionNotCascaded()
    {
        $this->metadata->addConstraint(new Valid(traverse: false));

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

    public function __construct(array $nested)
    {
        $this->nested = $nested;

        parent::__construct();
    }

    protected function getCompositeOption(): string
    {
        return 'nested';
    }
}
