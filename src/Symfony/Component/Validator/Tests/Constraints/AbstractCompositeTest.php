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

use Symfony\Component\Validator\Constraint;

/**
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 */
class AbstractCompositeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Constraint
     *
     * Constraint
     */
    private $simpleConstraint;

    /**
     * Setup method.
     */
    public function setUp()
    {
        parent::setUp();

        $this->simpleConstraint = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraint')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
    }

    /**
     * Collection groups: none
     * Constraint groups: none.
     *
     * Collection groups result: [Default]
     * Constraint groups result: [Default]
     */
    public function testEmptyGroups()
    {
        $composite = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraints\AbstractComposite')
            ->setConstructorArgs([
                'constraints' => [
                    $this->simpleConstraint,
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals(array_values($composite->groups), [
            $composite::DEFAULT_GROUP,
        ]);

        $constraint = $this->simpleConstraint;
        $this->assertEquals(array_values($this->simpleConstraint->groups), [
            $constraint::DEFAULT_GROUP,
        ]);
    }

    /**
     * Collection groups: [Default, Group1]
     * Constraint groups: none.
     *
     * Collection groups result: [Default, Group1]
     * Constraint groups result: [Default, Group1]
     */
    public function testCollectionGroups()
    {
        $composite = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraints\AbstractComposite')
            ->setConstructorArgs([
                [
                    'constraints' => [
                        $this->simpleConstraint,
                    ],
                    'groups' => [
                        'Default',
                        'Group1',
                    ],
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals(array_values($composite->groups), [
            $composite::DEFAULT_GROUP,
            'Group1',
        ]);

        $constraint = $this->simpleConstraint;
        $this->assertEquals(array_values($this->simpleConstraint->groups), [
            $constraint::DEFAULT_GROUP,
            'Group1',
        ]);
    }

    /**
     * Collection groups: none
     * Constraint groups: [Default, Group1].
     *
     * Collection groups result: [Default, Group1]
     * Constraint groups result: [Default, Group1]
     */
    public function testConstraintsGroups()
    {
        $this->simpleConstraint = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraint')
            ->setConstructorArgs([
                [
                    'groups' => [
                        'Default',
                        'Group1',
                    ],
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $composite = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraints\AbstractComposite')
            ->setConstructorArgs([
                [
                    'constraints' => [
                        $this->simpleConstraint,
                    ],
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals(array_values($composite->groups), [
            $composite::DEFAULT_GROUP,
            'Group1',
        ]);

        $constraint = $this->simpleConstraint;
        $this->assertEquals(array_values($this->simpleConstraint->groups), [
            $constraint::DEFAULT_GROUP,
            'Group1',
        ]);
    }

    /**
     * Collection groups: none
     * Constraint groups: [Default, Group1].
     *
     * Collection groups result: [Default, Group1]
     * Constraint groups result: [Default, Group1]
     */
    public function testBothGroups()
    {
        $this->simpleConstraint = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraint')
            ->setConstructorArgs([
                [
                    'groups' => [
                        'Default',
                        'Group1',
                    ],
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $composite = $this
            ->getMockBuilder('Symfony\Component\Validator\Constraints\AbstractComposite')
            ->setConstructorArgs([
                [
                    'constraints' => [
                        $this->simpleConstraint,
                    ],
                ],
            ])
            ->setMethods(null)
            ->getMock();

        $this->assertEquals(array_values($composite->groups), [
            $composite::DEFAULT_GROUP,
            'Group1',
        ]);

        $constraint = $this->simpleConstraint;
        $this->assertEquals(array_values($this->simpleConstraint->groups), [
            $constraint::DEFAULT_GROUP,
            'Group1',
        ]);
    }
}
