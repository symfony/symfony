<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Constraints\Composite;
use Symphony\Component\Validator\Constraints\NotBlank;
use Symphony\Component\Validator\Constraints\NotNull;
use Symphony\Component\Validator\Constraints\Valid;

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
    public function testMergeNestedGroupsIfNoExplicitParentGroup()
    {
        $constraint = new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            new NotBlank(array('groups' => array('Default', 'Strict'))),
        ));

        $this->assertEquals(array('Default', 'Strict'), $constraint->groups);
        $this->assertEquals(array('Default'), $constraint->constraints[0]->groups);
        $this->assertEquals(array('Default', 'Strict'), $constraint->constraints[1]->groups);
    }

    public function testSetImplicitNestedGroupsIfExplicitParentGroup()
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

    public function testExplicitNestedGroupsMustBeSubsetOfExplicitParentGroups()
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
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfExplicitNestedGroupsNotSubsetOfExplicitParentGroups()
    {
        new ConcreteComposite(array(
            'constraints' => array(
                new NotNull(array('groups' => array('Default', 'Foobar'))),
            ),
            'groups' => array('Default', 'Strict'),
        ));
    }

    public function testImplicitGroupNamesAreForwarded()
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

    public function testSingleConstraintsAccepted()
    {
        $nestedConstraint = new NotNull();
        $constraint = new ConcreteComposite($nestedConstraint);

        $this->assertEquals(array($nestedConstraint), $constraint->constraints);
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfNoConstraint()
    {
        new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            'NotBlank',
        ));
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testFailIfNoConstraintObject()
    {
        new ConcreteComposite(array(
            new NotNull(array('groups' => 'Default')),
            new \ArrayObject(),
        ));
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testValidCantBeNested()
    {
        new ConcreteComposite(array(
            new Valid(),
        ));
    }
}
