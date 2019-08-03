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
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\GroupSequence;

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
        $form = $this->createForm([
            'validation_groups' => 'group',
        ]);

        $this->assertEquals(['group'], $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToArray()
    {
        $form = $this->createForm([
            'validation_groups' => ['group1', 'group2'],
        ]);

        $this->assertEquals(['group1', 'group2'], $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToFalse()
    {
        $form = $this->createForm([
            'validation_groups' => false,
        ]);

        $this->assertEquals([], $form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToCallback()
    {
        $form = $this->createForm([
            'validation_groups' => [$this, 'testValidationGroupsCanBeSetToCallback'],
        ]);

        $this->assertIsCallable($form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToClosure()
    {
        $form = $this->createForm([
            'validation_groups' => function (FormInterface $form) { },
        ]);

        $this->assertIsCallable($form->getConfig()->getOption('validation_groups'));
    }

    public function testValidationGroupsCanBeSetToGroupSequence()
    {
        $form = $this->createForm([
            'validation_groups' => new GroupSequence(['group1', 'group2']),
        ]);

        $this->assertInstanceOf('Symfony\Component\Validator\Constraints\GroupSequence', $form->getConfig()->getOption('validation_groups'));
    }

    abstract protected function createForm(array $options = []);
}
