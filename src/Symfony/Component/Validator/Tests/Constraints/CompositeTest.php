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
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class ConcreteComposite extends Composite
{
    public $constraints = [];

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }

    public function getDefaultOption(): ?string
    {
        return 'constraints';
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
        ]);

        self::assertEquals(['Default'], $constraint->groups);
        self::assertEquals(['Default'], $constraint->constraints[0]->groups);
        self::assertEquals(['Default'], $constraint->constraints[1]->groups);
    }

    public function testNestedCompositeConstraintHasDefaultGroup()
    {
        $constraint = new ConcreteComposite([
            new ConcreteComposite(),
            new ConcreteComposite(),
        ]);

        self::assertEquals(['Default'], $constraint->groups);
        self::assertEquals(['Default'], $constraint->constraints[0]->groups);
        self::assertEquals(['Default'], $constraint->constraints[1]->groups);
    }

    public function testMergeNestedGroupsIfNoExplicitParentGroup()
    {
        $constraint = new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new NotBlank(['groups' => ['Default', 'Strict']]),
        ]);

        self::assertEquals(['Default', 'Strict'], $constraint->groups);
        self::assertEquals(['Default'], $constraint->constraints[0]->groups);
        self::assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
    }

    public function testSetImplicitNestedGroupsIfExplicitParentGroup()
    {
        $constraint = new ConcreteComposite([
            'constraints' => [
                new NotNull(),
                new NotBlank(),
            ],
            'groups' => ['Default', 'Strict'],
        ]);

        self::assertEquals(['Default', 'Strict'], $constraint->groups);
        self::assertEquals(['Default', 'Strict'], $constraint->constraints[0]->groups);
        self::assertEquals(['Default', 'Strict'], $constraint->constraints[1]->groups);
    }

    public function testExplicitNestedGroupsMustBeSubsetOfExplicitParentGroups()
    {
        $constraint = new ConcreteComposite([
            'constraints' => [
                new NotNull(['groups' => 'Default']),
                new NotBlank(['groups' => 'Strict']),
            ],
            'groups' => ['Default', 'Strict'],
        ]);

        self::assertEquals(['Default', 'Strict'], $constraint->groups);
        self::assertEquals(['Default'], $constraint->constraints[0]->groups);
        self::assertEquals(['Strict'], $constraint->constraints[1]->groups);
    }

    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroups()
    {
        self::expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            'constraints' => [
                new NotNull(['groups' => ['Default', 'Foobar']]),
            ],
            'groups' => ['Default', 'Strict'],
        ]);
    }

    public function testImplicitGroupNamesAreForwarded()
    {
        $constraint = new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new NotBlank(['groups' => 'Strict']),
        ]);

        $constraint->addImplicitGroupName('ImplicitGroup');

        self::assertEquals(['Default', 'Strict', 'ImplicitGroup'], $constraint->groups);
        self::assertEquals(['Default', 'ImplicitGroup'], $constraint->constraints[0]->groups);
        self::assertEquals(['Strict'], $constraint->constraints[1]->groups);
    }

    public function testSingleConstraintsAccepted()
    {
        $nestedConstraint = new NotNull();
        $constraint = new ConcreteComposite($nestedConstraint);

        self::assertEquals([$nestedConstraint], $constraint->constraints);
    }

    public function testFailIfNoConstraint()
    {
        self::expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            'NotBlank',
        ]);
    }

    public function testFailIfNoConstraintObject()
    {
        self::expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new NotNull(['groups' => 'Default']),
            new \ArrayObject(),
        ]);
    }

    public function testValidCantBeNested()
    {
        self::expectException(ConstraintDefinitionException::class);
        new ConcreteComposite([
            new Valid(),
        ]);
    }
}
