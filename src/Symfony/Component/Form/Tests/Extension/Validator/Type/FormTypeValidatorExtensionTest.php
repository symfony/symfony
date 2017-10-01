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
    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            FormTypeTest::TESTED_TYPE,
            null,
            array(
                'validation_groups' => 'group',
            )
        );
        $builder->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\FormType');
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
        $validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();

        $formTypeValidatorExtension = new FormTypeValidatorExtension($validator);
        $this->assertAttributeSame($validator, 'validator', $formTypeValidatorExtension);
    }

    public function testValidatorInterfaceUntilSymfony24()
    {
        // Mock of ValidatorInterface until apiVersion 2.4
        $validator = $this->getMockBuilder('Symfony\Component\Validator\ValidatorInterface')->getMock();

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
