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
use Symfony\Component\Validator\Constraints\AbstractComposite;

/**
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 */
class AbstractCompositeTest extends TestCase
{
    /**
     * @var Constraint
     *
     * Constraint
     */
    private $simpleConstraint;

    public function setUp()
    {
        parent::setUp();

        $this->simpleConstraint = $this
            ->getMockBuilder(Constraint::class)
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
            ->getMockBuilder(AbstractComposite::class)
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
            ->getMockBuilder(AbstractComposite::class)
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
            ->getMockBuilder(Constraint::class)
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
            ->getMockBuilder(AbstractComposite::class)
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
            ->getMockBuilder(Constraint::class)
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
            ->getMockBuilder(AbstractComposite::class)
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
