<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Form\Test\FormInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class BaseValidatorExtensionTest extends TypeTestCase
{
    public function testValidationGroupNullByDefault()
    {
        $form = $this->createForm();

        $this->assertNull($form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsTransformedToArray()
    {
        $form = $this->createForm(array(
            'validation_groups' => 'group',
        ));

        $this->assertEquals(array('group'), $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToArray()
    {
        $form = $this->createForm(array(
            'validation_groups' => array('group1', 'group2'),
        ));

        $this->assertEquals(array('group1', 'group2'), $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToFalse()
    {
        $form = $this->createForm(array(
            'validation_groups' => false,
        ));

        $this->assertEquals(array(), $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToCallback()
    {
        $form = $this->createForm(array(
            'validation_groups' => array($this, 'testValidationGroupsCanBeSetToCallback'),
        ));

        $this->assertInternalType('callable', $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToClosure()
    {
        $form = $this->createForm(array(
            'validation_groups' => function (FormInterface $form) { },
        ));

        $this->assertInternalType('callable', $form->getConfig()->getOption('validation_groups'));
    }

    abstract protected function createForm(array $options = array());
}
