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

use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Group;
use Symfony\Component\Validator\Constraints\Valid;

class GroupTest extends \PHPUnit_Framework_TestCase
{
    public function testConstraintsProperty()
    {
        $constraint = new ConstraintA();
        $group = new Group(array('groups' => 'GroupA', 'constraints' => $constraint));

        $this->assertSame(array(Constraint::PROPERTY_CONSTRAINT, Constraint::CLASS_CONSTRAINT), $group->getTargets());
        $this->assertSame(array('groups', 'constraints'), $group->getRequiredOptions());
        $this->assertSame(array($constraint), $group->constraints);
        $this->assertSame(array('GroupA'), $group->groups);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectNonConstraints()
    {
        new Group(array('groups' => 'GroupA', 'constraints' => array('foo')));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectEmbeddedGroupConstraints()
    {
        new Group(array(
            'groups' => 'GroupA',
            'constraints' => array(
                new ConstraintA(),
                new Group(array(
                    'groups' => 'GroupB',
                    'constraints' => array(new ConstraintB()),
                )),
            ),
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectEmbeddedValidConstraints()
    {
        new Group(array(
            'groups' => 'GroupA',
            'constraints' => array(new ConstraintA(), new Valid()),
        ));
    }
}
