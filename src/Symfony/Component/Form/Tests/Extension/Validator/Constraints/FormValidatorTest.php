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
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\Constraints\FormValidator;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\GlobalExecutionContext;
use Symfony\Component\Validator\ExecutionContext;

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
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => array('group1', 'group2'));
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testValidateConstraints()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
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
        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        // Then custom constraints
        $graphWalker->expects($this->at(2))
            ->method('walkConstraint')
            ->with($constraint1, $object, 'group1', 'data');
        $graphWalker->expects($this->at(3))
            ->method('walkConstraint')
            ->with($constraint2, $object, 'group2', 'data');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateIfParentWithoutCascadeValidation()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder('parent', null, array('cascade_validation' => false))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $options = array('validation_groups' => array('group1', 'group2'));
        $form = $this->getBuilder('name', '\stdClass', $options)->getForm();
        $parent->add($form);

        $form->setData($object);

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testValidateConstraintsEvenIfNoCascadeValidation()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
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

        $graphWalker->expects($this->at(0))
            ->method('walkConstraint')
            ->with($constraint1, $object, 'group1', 'data');
        $graphWalker->expects($this->at(1))
            ->method('walkConstraint')
            ->with($constraint2, $object, 'group2', 'data');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontValidateIfNotSynchronized()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
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
        $form->bind('foo');

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $expectedViolation = new ConstraintViolation(
            'invalid_message_key',
            array('{{ value }}' => 'foo', '{{ foo }}' => 'bar'),
            'Root',
            null,
            'foo',
            null,
            Form::ERR_INVALID
        );

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals($expectedViolation, $context->getViolations()->get(0));
    }

    public function testDontValidateConstraintsIfNotSynchronized()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
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
        $form->bind(array());

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testHandleCallbackValidationGroups()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => array($this, 'getValidationGroups'));
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testHandleClosureValidationGroups()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $options = array('validation_groups' => function(FormInterface $form){
            return array('group1', 'group2');
        });
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
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

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'group', 'foo.bar.data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedCallbackValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
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

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'foo.bar.data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'foo.bar.data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testUseInheritedClosureValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');

        $parentOptions = array(
            'validation_groups' => function(FormInterface $form){
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

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'foo.bar.data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'foo.bar.data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testAppendPropertyPath()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', '\stdClass')
            ->setData($object)
            ->getForm();

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'Default', 'foo.bar.data', true);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testDontWalkScalars()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();

        $form = $this->getBuilder()
            ->setData('scalar')
            ->getForm();

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());
    }

    public function testViolationIfExtraData()
    {
        $context = $this->getExecutionContext();

        $form = $this->getBuilder('parent', null, array('extra_fields_message' => 'Extra!'))
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->add($this->getBuilder('child'))
            ->getForm();

        $form->bind(array('foo' => 'bar'));

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Extra!', $context->getViolations()->get(0)->getMessage());
    }

    /**
     * @dataProvider getPostMaxSizeFixtures
     */
    public function testPostMaxSizeViolation($contentLength, $iniMax, $nbViolation, $msg)
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue($contentLength));
        $this->serverParams->expects($this->any())
            ->method('getNormalizedIniPostMaxSize')
            ->will($this->returnValue($iniMax));

        $context = $this->getExecutionContext();
        $options = array('post_max_size_message' => 'Max {{ max }}!');
        $form = $this->getBuilder('name', null, $options)->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount($nbViolation, $context->getViolations());
        if (null !== $msg) {
            $this->assertEquals($msg, $context->getViolations()->get(0)->getMessage());
        }
    }

    public function getPostMaxSizeFixtures()
    {
        return array(
            array(pow(1024, 3) + 1, '1G', 1, 'Max 1G!'),
            array(pow(1024, 3), '1G', 0, null),
            array(pow(1024, 2) + 1, '1M', 1, 'Max 1M!'),
            array(pow(1024, 2), '1M', 0, null),
            array(1024 + 1, '1K', 1, 'Max 1K!'),
            array(1024, '1K', 0, null),
            array(null, '1K', 0, null),
            array(1024, '', 0, null),
        );
    }

    public function testNoViolationIfNotRoot()
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->never())
            ->method('getNormalizedIniPostMaxSize');

        $context = $this->getExecutionContext();
        $parent = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper($this->getDataMapper())
            ->getForm();
        $form = $this->getForm();
        $parent->add($form);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
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

    private function getMockGraphWalker()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\GraphWalker')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockMetadataFactory()
    {
        return $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
    }

    private function getExecutionContext($propertyPath = null)
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $globalContext = new GlobalExecutionContext('Root', $graphWalker, $metadataFactory);

        return new ExecutionContext($globalContext, null, $propertyPath, null, null, null);
    }

    /**
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

    private function getForm($name = 'name', $dataClass = null)
    {
        return $this->getBuilder($name, $dataClass)->getForm();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getDataMapper()
    {
        return $this->getMock('Symfony\Component\Form\DataMapperInterface');
    }
}
