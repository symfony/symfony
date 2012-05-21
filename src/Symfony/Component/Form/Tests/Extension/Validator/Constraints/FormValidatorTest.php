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
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Validator\Constraint;
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
        $this->serverParams = $this->getMock('Symfony\Component\Form\Extension\Validator\Util\ServerParams');
        $this->validator = new FormValidator($this->serverParams);
    }

    public function testValidate()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', array('group1', 'group2'))
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
        $constraint1 = $this->getMock('Symfony\Component\Validator\Constraint');
        $constraint2 = $this->getMock('Symfony\Component\Validator\Constraint');

        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->setAttribute('constraints', array($constraint1, $constraint2))
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
            ->with($constraint1, $object, 'group2', 'data');
        $graphWalker->expects($this->at(4))
            ->method('walkConstraint')
            ->with($constraint2, $object, 'group1', 'data');
        $graphWalker->expects($this->at(5))
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

        $parent = $this->getBuilder()
            ->setAttribute('cascade_validation', false)
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->getForm();
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
        $constraint1 = $this->getMock('Symfony\Component\Validator\Constraint');
        $constraint2 = $this->getMock('Symfony\Component\Validator\Constraint');

        $parent = $this->getBuilder()
            ->setAttribute('cascade_validation', false)
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->setAttribute('constraints', array($constraint1, $constraint2))
            ->setData($object)
            ->getForm();
        $parent->add($form);

        $graphWalker->expects($this->at(0))
            ->method('walkConstraint')
            ->with($constraint1, $object, 'group1', 'data');
        $graphWalker->expects($this->at(1))
            ->method('walkConstraint')
            ->with($constraint1, $object, 'group2', 'data');
        $graphWalker->expects($this->at(2))
            ->method('walkConstraint')
            ->with($constraint2, $object, 'group1', 'data');
        $graphWalker->expects($this->at(3))
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

        $form = $this->getBuilder('name', '\stdClass')
            ->setData($object)
            ->setAttribute('invalid_message', 'Invalid!')
            ->appendClientTransformer(new CallbackTransformer(
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

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Invalid!', $context->getViolations()->get(0)->getMessage());
    }

    public function testDontValidateConstraintsIfNotSynchronized()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $constraint1 = $this->getMock('Symfony\Component\Validator\Constraint');
        $constraint2 = $this->getMock('Symfony\Component\Validator\Constraint');

        $form = $this->getBuilder('name', '\stdClass')
            ->setData($object)
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->setAttribute('constraints', array($constraint1, $constraint2))
            ->appendClientTransformer(new CallbackTransformer(
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
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', array($this, 'getValidationGroups'))
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
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', function(FormInterface $form){
                return array('group1', 'group2');
            })
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

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', 'group')
            ->setAttribute('cascade_validation', true)
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
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

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', array($this, 'getValidationGroups'))
            ->setAttribute('cascade_validation', true)
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
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

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', function(FormInterface $form){
                return array('group1', 'group2');
            })
            ->setAttribute('cascade_validation', true)
            ->getForm();
        $form = $this->getBuilder('name', '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
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

        $form = $this->getBuilder()
            ->add($this->getBuilder('child'))
            ->setAttribute('extra_fields_message', 'Extra!')
            ->getForm();

        $form->bind(array('foo' => 'bar'));

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Extra!', $context->getViolations()->get(0)->getMessage());
    }

    public function testViolationIfPostMaxSizeExceeded_GigaUpper()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(pow(1024, 3) + 1));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1G'));

        $context = $this->getExecutionContext();
        $form = $this->getBuilder()
            ->setAttribute('post_max_size_message', 'Max {{ max }}!')
            ->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Max 1G!', $context->getViolations()->get(0)->getMessage());
    }

    public function testViolationIfPostMaxSizeExceeded_GigaLower()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(pow(1024, 3) + 1));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1g'));

        $context = $this->getExecutionContext();
        $form = $this->getBuilder()
            ->setAttribute('post_max_size_message', 'Max {{ max }}!')
            ->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Max 1G!', $context->getViolations()->get(0)->getMessage());
    }

    public function testNoViolationIfPostMaxSizeNotExceeded_Giga()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(pow(1024, 3)));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1G'));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testViolationIfPostMaxSizeExceeded_Mega()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(pow(1024, 2) + 1));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1M'));

        $context = $this->getExecutionContext();
        $form = $this->getBuilder()
            ->setAttribute('post_max_size_message', 'Max {{ max }}!')
            ->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Max 1M!', $context->getViolations()->get(0)->getMessage());
    }

    public function testNoViolationIfPostMaxSizeNotExceeded_Mega()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(pow(1024, 2)));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1M'));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testViolationIfPostMaxSizeExceeded_Kilo()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1K'));

        $context = $this->getExecutionContext();
        $form = $this->getBuilder()
            ->setAttribute('post_max_size_message', 'Max {{ max }}!')
            ->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Max 1K!', $context->getViolations()->get(0)->getMessage());
    }

    public function testNoViolationIfPostMaxSizeNotExceeded_Kilo()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1024));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1K'));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testNoViolationIfNotRoot()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1K'));

        $context = $this->getExecutionContext();
        $parent = $this->getForm();
        $form = $this->getForm();
        $parent->add($form);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testNoViolationIfContentLengthNull()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(null));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1K'));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testTrimPostMaxSize()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('   1K    '));

        $context = $this->getExecutionContext();
        $form = $this->getBuilder()
            ->setAttribute('post_max_size_message', 'Max {{ max }}!')
            ->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(1, $context->getViolations());
        $this->assertEquals('Max 1K!', $context->getViolations()->get(0)->getMessage());
    }

    public function testNoViolationIfPostMaxSizeEmpty()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue('     '));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    public function testNoViolationIfPostMaxSizeNull()
    {
        $this->serverParams->expects($this->any())
            ->method('getContentLength')
            ->will($this->returnValue(1025));
        $this->serverParams->expects($this->any())
            ->method('getPostMaxSize')
            ->will($this->returnValue(null));

        $context = $this->getExecutionContext();
        $form = $this->getForm();

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
    private function getBuilder($name = 'name', $dataClass = null)
    {
        $builder = new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory);
        $builder->setAttribute('constraints', array());

        return $builder;
    }

    private function getForm($name = 'name', $dataClass = null)
    {
        return $this->getBuilder($name, $dataClass)->getForm();
    }
}
