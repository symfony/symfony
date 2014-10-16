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

use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationList;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            'form',
            null,
            array(
                'validation_groups' => 'group',
            )
        );
        $builder->add('firstName', 'form');
        $form = $builder->getForm();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->equalTo($form))
            ->will($this->returnValue(new ConstraintViolationList()));

        // specific data is irrelevant
        $form->submit(array());
    }

    public function testValidConstraint()
    {
        $form = $this->createForm(array('constraints' => $valid = new Valid()));

        $this->assertSame(array($valid), $form->getConfig()->getOption('constraints'));
    }

    /**
     * @group legacy
     */
    public function testCascadeValidationCanBeSetToTrue()
    {
        $form = $this->createForm(array('cascade_validation' => true));

        $this->assertTrue($form->getConfig()->getOption('cascade_validation'));
    }

    /**
     * @group legacy
     */
    public function testCascadeValidationCanBeSetToFalse()
    {
        $form = $this->createForm(array('cascade_validation' => false));

        $this->assertFalse($form->getConfig()->getOption('cascade_validation'));
    }

    public function testValidatorInterfaceSinceSymfony25()
    {
        // Mock of ValidatorInterface since apiVersion 2.5
        $validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');

        $formTypeValidatorExtension = new FormTypeValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $formTypeValidatorExtension);
    }

    public function testValidatorInterfaceUntilSymfony24()
    {
        // Mock of ValidatorInterface until apiVersion 2.4
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');

        $formTypeValidatorExtension = new FormTypeValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $formTypeValidatorExtension);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidValidatorInterface()
    {
        new FormTypeValidatorExtension(null);
    }

    protected function createForm(array $options = array())
    {
        return $this->factory->create('form', null, $options);
    }
}
