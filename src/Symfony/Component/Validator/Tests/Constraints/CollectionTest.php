<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionTest extends TestCase
{
    public function testRejectInvalidFieldsOption()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Collection([
            'fields' => 'foo',
        ]);
    }

    public function testRejectNonConstraints()
    {
        $this->expectException(InvalidOptionsException::class);
        new Collection([
            'foo' => 'bar',
        ]);
    }

    public function testRejectValidConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Valid(),
        ]);
    }

    public function testRejectValidConstraintWithinOptional()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Optional(new Valid()),
        ]);
    }

    public function testRejectValidConstraintWithinRequired()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Required(new Valid()),
        ]);
    }

    public function testAcceptOptionalConstraintAsOneElementArray()
    {
        $collection1 = new Collection([
            'fields' => [
                'alternate_email' => [
                    new Optional(new Email()),
                ],
            ],
        ]);

        $collection2 = new Collection([
            'fields' => [
                'alternate_email' => new Optional(new Email()),
            ],
        ]);

        $this->assertEquals($collection1, $collection2);
    }

    public function testAcceptRequiredConstraintAsOneElementArray()
    {
        $collection1 = new Collection([
            'fields' => [
                'alternate_email' => [
                    new Required(new Email()),
                ],
            ],
        ]);

        $collection2 = new Collection([
            'fields' => [
                'alternate_email' => new Required(new Email()),
            ],
        ]);

        $this->assertEquals($collection1, $collection2);
    }

    public function testConstraintHasDefaultGroupWithOptionalValues()
    {
        $constraint = new Collection([
            'foo' => new Required(),
            'bar' => new Optional(),
        ]);

        $this->assertEquals(['Default'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->fields['foo']->groups);
        $this->assertEquals(['Default'], $constraint->fields['bar']->groups);
    }

    public function testOnlySomeKeysAreKnowOptions()
    {
        $constraint = new Collection([
            'fields' => [new Required()],
            'properties' => [new Required()],
            'catalog' => [new Optional()],
        ]);

        $this->assertArrayHasKey('fields', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['fields']);
        $this->assertArrayHasKey('properties', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['properties']);
        $this->assertArrayHasKey('catalog', $constraint->fields);
        $this->assertInstanceOf(Optional::class, $constraint->fields['catalog']);
    }

    public function testAllKeysAreKnowOptions()
    {
        $constraint = new Collection([
            'fields' => [
                'fields' => [new Required()],
                'properties' => [new Required()],
                'catalog' => [new Optional()],
            ],
            'allowExtraFields' => true,
            'extraFieldsMessage' => 'foo bar baz',
        ]);

        $this->assertArrayHasKey('fields', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['fields']);
        $this->assertArrayHasKey('properties', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['properties']);
        $this->assertArrayHasKey('catalog', $constraint->fields);
        $this->assertInstanceOf(Optional::class, $constraint->fields['catalog']);

        $this->assertTrue($constraint->allowExtraFields);
        $this->assertSame('foo bar baz', $constraint->extraFieldsMessage);
    }

    public function testEmptyFields()
    {
        $constraint = new Collection([], [], null, true, null, 'foo bar baz');

        $this->assertTrue($constraint->allowExtraFields);
        $this->assertSame('foo bar baz', $constraint->extraFieldsMessage);
    }

    public function testEmptyFieldsInOptions()
    {
        $constraint = new Collection([
            'fields' => [],
            'allowExtraFields' => true,
            'extraFieldsMessage' => 'foo bar baz',
        ]);

        $this->assertSame([], $constraint->fields);
        $this->assertTrue($constraint->allowExtraFields);
        $this->assertSame('foo bar baz', $constraint->extraFieldsMessage);
    }

    /**
     * @testWith [[]]
     *           [null]
     */
    public function testEmptyConstraintListForField(?array $fieldConstraint)
    {
        $constraint = new Collection(
            [
                'foo' => $fieldConstraint,
            ],
            null,
            null,
            true,
            null,
            'foo bar baz'
        );

        $this->assertArrayHasKey('foo', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['foo']);
        $this->assertTrue($constraint->allowExtraFields);
        $this->assertSame('foo bar baz', $constraint->extraFieldsMessage);
    }

    /**
     * @testWith [[]]
     *           [null]
     */
    public function testEmptyConstraintListForFieldInOptions(?array $fieldConstraint)
    {
        $constraint = new Collection([
            'fields' => [
                'foo' => $fieldConstraint,
            ],
            'allowExtraFields' => true,
            'extraFieldsMessage' => 'foo bar baz',
        ]);

        $this->assertArrayHasKey('foo', $constraint->fields);
        $this->assertInstanceOf(Required::class, $constraint->fields['foo']);
        $this->assertTrue($constraint->allowExtraFields);
        $this->assertSame('foo bar baz', $constraint->extraFieldsMessage);
    }
}
