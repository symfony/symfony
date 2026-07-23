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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class ConcreteComposite extends Composite
{
    public array|Constraint $constraints = [];

    public function __construct(array|Constraint $constraints = [], public array|Constraint $otherNested = [], ?array $groups = null)
    {
        $this->constraints = $constraints;

        parent::__construct(null, $groups);
    }

    protected function getCompositeOption(): array
    {
        return ['constraints', 'otherNested'];
    }
}

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeTest extends TestCase
{
    public function testConstraintHasDefaultGroup()
    {
        $constraint = new ConcreteComposite([
            new NotNull(),
            new NotBlank(),
        ], [
            new Length(exactly: 10),
        ]);

        $this->assertEquals(['Default'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default'], $constraint->constraints[1]->groups);
        $this->assertEquals(['Default'], $constraint->otherNested[0]->groups);
    }

    public function testNestedCompositeConstraintHasDefaultGroup()
    {
        $constraint = new ConcreteComposite([
            new ConcreteComposite(),
            new ConcreteComposite(),
        ]);

        $this->assertEquals(['Default'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default'], $constraint->constraints[1]->groups);
    }

    public function testMergeNestedGroupsIfNoExplicitParentGroup()
    {
        $constraint = new ConcreteComposite([
            new NotNull(groups: ['Default']),
            new NotBlank(groups: ['Default', 'Strict']),
        ], [
            new Length(exactly: 10, groups: ['Default', 'Strict']),
        ]);

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->otherNested[0]->groups);
    }

    public function testSetImplicitNestedGroupsIfExplicitParentGroup()
    {
        $constraint = new ConcreteComposite(
            [
                new NotNull(),
                new NotBlank(),
            ],
            new Length(exactly: 10),
            ['Default', 'Strict'],
        );

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
        $this->assertEquals(['Default', 'Strict'], $constraint->otherNested[0]->groups);
    }

    public function testExplicitNestedGroupsMustBeSubsetOfExplicitParentGroups()
    {
        $constraint = new ConcreteComposite(
            [
                new NotNull(groups: ['Default']),
                new NotBlank(groups: ['Strict']),
            ],
            new Length(exactly: 10, groups: ['Strict']),
            ['Default', 'Strict']
        );

        $this->assertEquals(['Default', 'Strict'], $constraint->groups);
        $this->assertEquals(['Default'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Strict'], $constraint->constraints[1]->groups);
        $this->assertEquals(['Strict'], $constraint->otherNested[0]->groups);
    }

    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroups()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite(new NotNull(groups: ['Default', 'Foobar']), [], ['Default', 'Strict']);
    }

    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroupsInOtherNested()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite(new NotNull(groups: ['Default']), new NotNull(groups: ['Default', 'Foobar']), ['Default', 'Strict']);
    }

    public function testImplicitGroupNamesAreForwarded()
    {
        $constraint = new ConcreteComposite([
            new NotNull(groups: ['Default']),
            new NotBlank(groups: ['Strict']),
        ], [
            new Length(exactly: 10, groups: ['Default']),
        ]);

        $constraint->addImplicitGroupName('ImplicitGroup');

        $this->assertEquals(['Default', 'Strict', 'ImplicitGroup'], $constraint->groups);
        $this->assertEquals(['Default', 'ImplicitGroup'], $constraint->constraints[0]->groups);
        $this->assertEquals(['Strict'], $constraint->constraints[1]->groups);
        $this->assertEquals(['Default', 'ImplicitGroup'], $constraint->otherNested[0]->groups);
    }

    public function testSingleConstraintsAccepted()
    {
        $nestedConstraint = new NotNull();
        $otherNestedConstraint = new Length(exactly: 10);
        $constraint = new ConcreteComposite($nestedConstraint, $otherNestedConstraint);

        $this->assertEquals([$nestedConstraint], $constraint->constraints);
        $this->assertEquals([$otherNestedConstraint], $constraint->otherNested);
    }

    public function testFailIfNoConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(groups: ['Default']),
            'NotBlank',
        ]);
    }

    public function testFailIfNoConstraintInAnotherNested()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([new NotNull()], [
            new NotNull(groups: ['Default']),
            'NotBlank',
        ]);
    }

    public function testFailIfNoConstraintObject()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(groups: ['Default']),
            new \ArrayObject(),
        ]);
    }

    public function testFailIfNoConstraintObjectInAnotherNested()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([new NotNull()], [
            new NotNull(groups: ['Default']),
            new \ArrayObject(),
        ]);
    }

    public function testValidCantBeNested()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new Valid(),
        ]);
    }

    public function testValidCantBeNestedInAnotherNested()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([new NotNull()], [new Valid()]);
    }
}
