<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Validator\Type;

use Symphony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symphony\Component\Form\Extension\Validator\ValidatorExtension;
use Symphony\Component\Form\Forms;
use Symphony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symphony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symphony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symphony\Component\Validator\Constraints\Email;
use Symphony\Component\Validator\Constraints\GroupSequence;
use Symphony\Component\Validator\Constraints\Length;
use Symphony\Component\Validator\Constraints\Valid;
use Symphony\Component\Validator\ConstraintViolationList;
use Symphony\Component\Validator\Validation;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            FormTypeTest::TESTED_TYPE,
            null,
            array(
                'validation_groups' => 'group',
            )
        );
        $builder->add('firstName', FormTypeTest::TESTED_TYPE);
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

    public function testValidatorInterface()
    {
        $validator = $this->getMockBuilder('Symphony\Component\Validator\Validator\ValidatorInterface')->getMock();

        $formTypeValidatorExtension = new FormTypeValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $formTypeValidatorExtension);
    }

    public function testGroupSequenceWithConstraintsOption()
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, null, (array('validation_groups' => new GroupSequence(array('First', 'Second')))))
            ->add('field', TextTypeTest::TESTED_TYPE, array(
                'constraints' => array(
                    new Length(array('min' => 10, 'groups' => array('First'))),
                    new Email(array('groups' => array('Second'))),
                ),
            ))
        ;

        $form->submit(array('field' => 'wrong'));

        $this->assertCount(1, $form->getErrors(true));
    }

    protected function createForm(array $options = array())
    {
        return $this->factory->create(FormTypeTest::TESTED_TYPE, null, $options);
    }
}
