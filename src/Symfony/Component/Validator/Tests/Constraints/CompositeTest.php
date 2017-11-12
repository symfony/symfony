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

class ConcreteComposite extends Composite
{
    public $constraints;

    protected function getCompositeOption()
    {
        return 'constraints';
    }

    public function getDefaultOption()
    {
        return 'constraints';
    }
}

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeTest extends TestCase
{
    public function testMergeNestedGroupsIfNoExplicitParentGroup(): void
    {
        $constraint = new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            new NotBlank(array('groups' => array('Default', 'Strict'))),
        ));

        $this->assertEquals(array('Default', 'Strict'), $constraint->groups);
        $this->assertEquals(array('Default'), $constraint->constraints[0]->groups);
        $this->assertEquals(array('Default', 'Strict'), $constraint->constraints[1]->groups);
    }

    public function testSetImplicitNestedGroupsIfExplicitParentGroup(): void
    {
        $constraint = new ConcreteComposite(array(
            'constraints' => array(
                new NotNull(),
                new NotBlank(),
            ),
            'groups' => array('Default', 'Strict'),
        ));

        $this->assertEquals(array('Default', 'Strict'), $constraint->groups);
        $this->assertEquals(array('Default', 'Strict'), $constraint->constraints[0]->groups);
        $this->assertEquals(array('Default', 'Strict'), $constraint->constraints[1]->groups);
    }

    public function testExplicitNestedGroupsMustBeSubsetOfExplicitParentGroups(): void
    {
        $constraint = new ConcreteComposite(array(
            'constraints' => array(
                new NotNull(array('groups' => 'Default')),
                new NotBlank(array('groups' => 'Strict')),
            ),
            'groups' => array('Default', 'Strict'),
        ));

        $this->assertEquals(array('Default', 'Strict'), $constraint->groups);
        $this->assertEquals(array('Default'), $constraint->constraints[0]->groups);
        $this->assertEquals(array('Strict'), $constraint->constraints[1]->groups);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroups(): void
    {
        new ConcreteComposite(array(
            'constraints' => array(
                new NotNull(array('groups' => array('Default', 'Foobar'))),
            ),
            'groups' => array('Default', 'Strict'),
        ));
    }

    public function testImplicitGroupNamesAreForwarded(): void
    {
        $constraint = new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            new NotBlank(array('groups' => 'Strict')),
        ));

        $constraint->addImplicitGroupName('ImplicitGroup');

        $this->assertEquals(array('Default', 'Strict', 'ImplicitGroup'), $constraint->groups);
        $this->assertEquals(array('Default', 'ImplicitGroup'), $constraint->constraints[0]->groups);
        $this->assertEquals(array('Strict'), $constraint->constraints[1]->groups);
    }

    public function testSingleConstraintsAccepted(): void
    {
        $nestedConstraint = new NotNull();
        $constraint = new ConcreteComposite($nestedConstraint);

        $this->assertEquals(array($nestedConstraint), $constraint->constraints);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfNoConstraint(): void
    {
        new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            'NotBlank',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfNoConstraintObject(): void
    {
        new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            new \ArrayObject(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testValidCantBeNested(): void
    {
        new ConcreteComposite(array(
            new Valid(),
        ));
    }
}
