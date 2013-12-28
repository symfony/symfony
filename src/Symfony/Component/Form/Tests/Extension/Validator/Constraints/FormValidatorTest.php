<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Constraints;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\Constraints\FormValidator;
use Symfony\Component\Form\SubmitButtonBuilder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $factory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $serverParams;

    /**
     * @var FormValidator
     */
    private $validator;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\Event')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->serverParams = $this->getMock(
            'Symfony\Component\Form\Extension\Validator\Util\ServerParams',
            array('getNormalizedIniPostMaxSize', 'getContentLength')
        );
        $this->validator = new FormValidator($this->serverParams);
    }

    public function testValidate()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => array('group1', 'group2'));
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testValidateConstraints()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $constraint1 = new NotNull(array('groups' => array('group1', 'group2')));
        $constraint2 = new NotBlank(array('groups' => 'group2'));

        $options = array(
            'validation_groups' => array('group1', 'group2'),
            'constraints' => array($constraint1, $constraint2),
        );
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        // First default constraints
        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        // Then custom constraints
        $context->expects($this->at(2))
            ->method('validateValue')
            ->with($object, $constraint1, 'data', 'group1');
        $context->expects($this->at(3))
            ->method('validateValue')
            ->with($object, $constraint2, 'data', 'group2');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateIfParentWithoutCascadeValidation()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder('parent', null, array('cascade_validation' => false))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $options = array('validation_groups' => array('group1', 'group2'));
        $form = $this->getBuilder('name', '\stdClass', $options)->getForm();
        $parent->add($form);

        $form->setData($object);

        $context->expects($this->never())
            ->method('validate');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testValidateConstraintsEvenIfNoCascadeValidation()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $constraint1 = new NotNull(array('groups' => array('group1', 'group2')));
        $constraint2 = new NotBlank(array('groups' => 'group2'));

        $parent = $this->getBuilder('parent', null, array('cascade_validation' => false))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $options = array(
            'validation_groups' => array('group1', 'group2'),
            'constraints' => array($constraint1, $constraint2),
        );
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();
        $parent->add($form);

        $context->expects($this->at(0))
            ->method('validateValue')
            ->with($object, $constraint1, 'data', 'group1');
        $context->expects($this->at(1))
            ->method('validateValue')
            ->with($object, $constraint2, 'data', 'group2');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateIfNoValidationGroups()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $form = $this->getBuilder('name', '\stdClass', array(
                'validation_groups' => array(),
            ))
            ->setData($object)
            ->getForm();

        $form->setData($object);

        $context->expects($this->never())
            ->method('validate');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateConstraintsIfNoValidationGroups()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $constraint1 = $this->getMock('Symfony\Component\Validator\Constraint');
        $constraint2 = $this->getMock('Symfony\Component\Validator\Constraint');

        $options = array(
            'validation_groups' => array(),
            'constraints' => array($constraint1, $constraint2),
        );
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        // Launch transformer
        $form->submit(array());

        $context->expects($this->never())
            ->method('validate');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateIfNotSynchronized()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $form = $this->getBuilder('name', '\stdClass', array(
                'invalid_message' => 'invalid_message_key',
                // Invalid message parameters must be supported, because the
                // invalid message can be a translation key
                // see https://github.com/symfony/symfony/issues/5144
                'invalid_message_parameters' => array('{{ foo }}' => 'bar'),
            ))
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                function ($data) { return $data; },
                function () { throw new TransformationFailedException(); }
            ))
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $context->expects($this->never())
            ->method('validate');

        $context->expects($this->once())
            ->method('addViolation')
            ->with(
                'invalid_message_key',
                array('{{ value }}' => 'foo', '{{ foo }}' => 'bar'),
                'foo'
            );
        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testAddInvalidErrorEvenIfNoValidationGroups()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $form = $this->getBuilder('name', '\stdClass', array(
                'invalid_message' => 'invalid_message_key',
                // Invalid message parameters must be supported, because the
                // invalid message can be a translation key
                // see https://github.com/symfony/symfony/issues/5144
                'invalid_message_parameters' => array('{{ foo }}' => 'bar'),
                'validation_groups' => array(),
            ))
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                    function ($data) { return $data; },
                    function () { throw new TransformationFailedException(); }
                ))
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $context->expects($this->never())
            ->method('validate');

        $context->expects($this->once())
            ->method('addViolation')
            ->with(
                'invalid_message_key',
                array('{{ value }}' => 'foo', '{{ foo }}' => 'bar'),
                'foo'
            );
        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateConstraintsIfNotSynchronized()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $constraint1 = $this->getMock('Symfony\Component\Validator\Constraint');
        $constraint2 = $this->getMock('Symfony\Component\Validator\Constraint');

        $options = array(
            'validation_groups' => array('group1', 'group2'),
            'constraints' => array($constraint1, $constraint2),
        );
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                function ($data) { return $data; },
                function () { throw new TransformationFailedException(); }
            ))
            ->getForm();

        // Launch transformer
        $form->submit(array());

        $context->expects($this->never())
            ->method('validate');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    // https://github.com/symfony/symfony/issues/4359
    public function testDontMarkInvalidIfAnyChildIsNotSynchronized()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $failingTransformer = new CallbackTransformer(
            function ($data) { return $data; },
            function () { throw new TransformationFailedException(); }
        );

        $form = $this->getBuilder('name', '\stdClass')
            ->setData($object)
            ->addViewTransformer($failingTransformer)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add(
                $this->getBuilder('child')
                    ->addViewTransformer($failingTransformer)
            )
            ->getForm();

        // Launch transformer
        $form->submit(array('child' => 'foo'));

        $context->expects($this->never())
            ->method('addViolation');
        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testHandleCallbackValidationGroups()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => array($this, 'getValidationGroups'));
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontExecuteFunctionNames()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => 'header');
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $context->expects($this->once())
            ->method('validate')
            ->with($object, 'data', 'header', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testHandleClosureValidationGroups()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => function (FormInterface $form) {
            return array('group1', 'group2');
        });
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseValidationGroupOfClickedButton()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder('parent', null, array('cascade_validation' => true))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getForm('name', '\stdClass', array(
            'validation_groups' => 'form_group',
        ));

        $parent->add($form);
        $parent->add($this->getSubmitButton('submit', array(
            'validation_groups' => 'button_group',
        )));

        $parent->submit(array('name' => $object, 'submit' => ''));

        $context->expects($this->once())
            ->method('validate')
            ->with($object, 'data', 'button_group', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontUseValidationGroupOfUnclickedButton()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder('parent', null, array('cascade_validation' => true))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getForm('name', '\stdClass', array(
            'validation_groups' => 'form_group',
        ));

        $parent->add($form);
        $parent->add($this->getSubmitButton('submit', array(
            'validation_groups' => 'button_group',
        )));

        $form->setData($object);

        $context->expects($this->once())
            ->method('validate')
            ->with($object, 'data', 'form_group', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedValidationGroup()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parentOptions = array(
            'validation_groups' => 'group',
            'cascade_validation' => true,
        );
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')->getForm();
        $parent->add($form);

        $form->setData($object);

        $context->expects($this->once())
            ->method('validate')
            ->with($object, 'data', 'group', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedCallbackValidationGroup()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parentOptions = array(
            'validation_groups' => array($this, 'getValidationGroups'),
            'cascade_validation' => true,
        );
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')->getForm();
        $parent->add($form);

        $form->setData($object);

        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedClosureValidationGroup()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');

        $parentOptions = array(
            'validation_groups' => function (FormInterface $form) {
                return array('group1', 'group2');
            },
            'cascade_validation' => true,
        );
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')->getForm();
        $parent->add($form);

        $form->setData($object);

        $context->expects($this->at(0))
            ->method('validate')
            ->with($object, 'data', 'group1', true);
        $context->expects($this->at(1))
            ->method('validate')
            ->with($object, 'data', 'group2', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testAppendPropertyPath()
    {
        $context = $this->getMockExecutionContext();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', '\stdClass')
            ->setData($object)
            ->getForm();

        $context->expects($this->once())
            ->method('validate')
            ->with($object, 'data', 'Default', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontWalkScalars()
    {
        $context = $this->getMockExecutionContext();

        $form = $this->getBuilder()
            ->setData('scalar')
            ->getForm();

        $context->expects($this->never())
            ->method('validate');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testViolationIfExtraData()
    {
        $context = $this->getMockExecutionContext();

        $form = $this->getBuilder('parent', null, array('extra_fields_message' => 'Extra!'))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder('child'))
            ->getForm();

        $form->submit(array('foo' => 'bar'));

        $context->expects($this->once())
            ->method('addViolation')
            ->with(
                'Extra!',
                array('{{ extra_fields }}' => 'foo'),
                array('foo' => 'bar')
            );
        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    /**
     * @dataProvider getPostMaxSizeFixtures
     */
    public function testPostMaxSizeViolation($contentLength, $iniMax, $nbViolation, array $params = array())
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue($contentLength));
        $this->serverParams->expects($this->any())
            ->method('getNormalizedIniPostMaxSize')
            ->will($this->returnValue($iniMax));

        $context = $this->getMockExecutionContext();
        $options = array('post_max_size_message' => 'Max {{ max }}!');
        $form = $this->getBuilder('name', null, $options)->getForm();

        for ($i = 0; $i < $nbViolation; ++$i) {
            if (0 === $i && count($params) > 0) {
                $context->expects($this->at($i))
                    ->method('addViolation')
                    ->with($options['post_max_size_message'], $params);
            } else {
                $context->expects($this->at($i))
                    ->method('addViolation');
            }
        }

        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function getPostMaxSizeFixtures()
    {
        return array(
            array(pow(1024, 3) + 1, '1G', 1, array('{{ max }}' => '1G')),
            array(pow(1024, 3), '1G', 0),
            array(pow(1024, 2) + 1, '1M', 1, array('{{ max }}' => '1M')),
            array(pow(1024, 2), '1M', 0),
            array(1024 + 1, '1K', 1, array('{{ max }}' => '1K')),
            array(1024, '1K', 0),
            array(null, '1K', 0),
            array(1024, '', 0),
            array(1024, 0, 0),
        );
    }

    public function testNoViolationIfNotRoot()
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->never())
            ->method('getNormalizedIniPostMaxSize');

        $context = $this->getMockExecutionContext();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getForm();
        $parent->add($form);

        $context->expects($this->never())
            ->method('addViolation');
        $context->expects($this->never())
            ->method('addViolationAt');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    /**
     * Access has to be public, as this method is called via callback array
     * in {@link testValidateFormDataCanHandleCallbackValidationGroups()}
     * and {@link testValidateFormDataUsesInheritedCallbackValidationGroup()}
     */
    public function getValidationGroups(FormInterface $form)
    {
        return array('group1', 'group2');
    }

    private function getMockExecutionContext()
    {
        return $this->getMock('Symfony\Component\Validator\ExecutionContextInterface');
    }

    /**
     * @param string $name
     * @param string $dataClass
     * @param array  $options
     *
     * @return FormBuilder
     */
    private function getBuilder($name = 'name', $dataClass = null, array $options = array())
    {
        $options = array_replace(array(
            'constraints' => array(),
            'invalid_message_parameters' => array(),
        ), $options);

        return new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory, $options);
    }

    private function getForm($name = 'name', $dataClass = null, array $options = array())
    {
        return $this->getBuilder($name, $dataClass, $options)->getForm();
    }

    private function getSubmitButton($name = 'name', array $options = array())
    {
        $builder = new SubmitButtonBuilder($name, $options);

        return $builder->getForm();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getDataMapper()
    {
        return $this->getMock('Symfony\Component\Form\DataMapperInterface');
    }
}
