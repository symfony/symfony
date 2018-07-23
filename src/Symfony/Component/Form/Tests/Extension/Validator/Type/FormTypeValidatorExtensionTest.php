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
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;

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
        $validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();

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
