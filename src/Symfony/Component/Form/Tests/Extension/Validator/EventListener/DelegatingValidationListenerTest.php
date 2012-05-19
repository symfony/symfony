<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\EventListener;

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Extension\Validator\EventListener\DelegatingValidationListener;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\GlobalExecutionContext;
use Symfony\Component\Validator\ExecutionContext;

class DelegatingValidationListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $factory;

    private $builder;

    private $delegate;

    private $listener;

    private $message;

    private $params;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\Event')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->delegate = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->listener = new DelegatingValidationListener($this->delegate);
        $this->message = 'Message';
        $this->params = array('foo' => 'bar');
    }

    protected function getMockGraphWalker()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\GraphWalker')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getMockMetadataFactory()
    {
        return $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
    }

    protected function getMockTransformer()
    {
        return $this->getMock('Symfony\Component\Form\DataTransformerInterface', array(), array(), '', false, false);
    }

    protected function getExecutionContext($propertyPath = null)
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $globalContext = new GlobalExecutionContext('Root', $graphWalker, $metadataFactory);

        return new ExecutionContext($globalContext, null, $propertyPath, null, null, null);
    }

    protected function getConstraintViolation($propertyPath)
    {
        return new ConstraintViolation($this->message, $this->params, null, $propertyPath, null);
    }

    protected function getFormError()
    {
        return new FormError($this->message, $this->params);
    }

    protected function getBuilder($name = 'name', $propertyPath = null, $dataClass = null)
    {
        $builder = new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory);
        $builder->setPropertyPath(new PropertyPath($propertyPath ?: $name));
        $builder->setAttribute('error_mapping', array());
        $builder->setErrorBubbling(false);
        $builder->setMapped(true);

        return $builder;
    }

    protected function getForm($name = 'name', $propertyPath = null, $dataClass = null)
    {
        return $this->getBuilder($name, $propertyPath, $dataClass)->getForm();
    }

    protected function getMockForm()
    {
        return $this->getMock('Symfony\Component\Form\Tests\FormInterface');
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

    public function testUseValidateValueWhenValidationConstraintExist()
    {
        $constraint = $this->getMockForAbstractClass('Symfony\Component\Validator\Constraint');
        $form = $this
            ->getBuilder('name')
            ->setAttribute('validation_constraint', $constraint)
            ->getForm();

        $this->delegate->expects($this->once())->method('validateValue');

        $this->listener->validateForm(new DataEvent($form, null));
    }

    // More specific mapping tests can be found in ViolationMapperTest
    public function testFormErrorMapping()
    {
        $parent = $this->getForm();
        $child = $this->getForm('street');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[street].data.constrainedProp')
            )));

        $this->listener->validateForm(new DataEvent($parent, null));

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    // More specific mapping tests can be found in ViolationMapperTest
    public function testDataErrorMapping()
    {
        $parent = $this->getForm();
        $child = $this->getForm('firstName');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.firstName.constrainedProp')
            )));

        $this->listener->validateForm(new DataEvent($parent, null));

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testValidateFormData()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $form->setData($object);

        DelegatingValidationListener::validateFormData($form, $context);
    }

    public function testValidateFormDataCanHandleCallbackValidationGroups()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', array($this, 'getValidationGroups'))
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $form->setData($object);

        DelegatingValidationListener::validateFormData($form, $context);
    }

    public function testValidateFormDataCanHandleClosureValidationGroups()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', function(FormInterface $form){
                return array('group1', 'group2');
            })
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $form->setData($object);

        DelegatingValidationListener::validateFormData($form, $context);
    }

    public function testValidateFormDataUsesInheritedValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', 'group')
            ->getForm();
        $child = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
        $parent->add($child);

        $child->setData($object);

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'group', 'foo.bar.data', true);

        DelegatingValidationListener::validateFormData($child, $context);
    }

    public function testValidateFormDataUsesInheritedCallbackValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', array($this, 'getValidationGroups'))
            ->getForm();
        $child = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
        $parent->add($child);

        $child->setData($object);

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'foo.bar.data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'foo.bar.data', true);

        DelegatingValidationListener::validateFormData($child, $context);
    }

    public function testValidateFormDataUsesInheritedClosureValidationGroup()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', function(FormInterface $form){
                return array('group1', 'group2');
            })
            ->getForm();
        $child = $this->getBuilder('name', null, '\stdClass')
            ->setAttribute('validation_groups', null)
            ->getForm();
        $parent->add($child);

        $child->setData($object);

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'foo.bar.data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'foo.bar.data', true);

        DelegatingValidationListener::validateFormData($child, $context);
    }

    public function testValidateFormDataAppendsPropertyPath()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $object = $this->getMock('\stdClass');
        $form = $this->getForm('name', null, '\stdClass');

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'Default', 'foo.bar.data', true);

        $form->setData($object);

        DelegatingValidationListener::validateFormData($form, $context);
    }

    public function testValidateFormDataDoesNotWalkScalars()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $clientTransformer = $this->getMockTransformer();

        $form = $this->getBuilder()
            ->appendClientTransformer($clientTransformer)
            ->getForm();

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $clientTransformer->expects($this->atLeastOnce())
            ->method('reverseTransform')
            ->will($this->returnValue('foobar'));

        $form->bind(array('foo' => 'bar')); // reverse transformed to "foobar"

        DelegatingValidationListener::validateFormData($form, $context);
    }

    public function testValidateFormChildren()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $form = $this->getBuilder()
            ->setAttribute('cascade_validation', true)
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->getForm();
        $form->add($this->getForm('firstName'));

        $graphWalker->expects($this->once())
            ->method('walkReference')
            // validation happens in Default group, because the Callback
            // constraint is in the Default group as well
            ->with($form->getChildren(), Constraint::DEFAULT_GROUP, 'children', true);

        DelegatingValidationListener::validateFormChildren($form, $context);
    }

    public function testValidateFormChildrenAppendsPropertyPath()
    {
        $context = $this->getExecutionContext('foo.bar');
        $graphWalker = $context->getGraphWalker();
        $form = $this->getBuilder()
            ->setAttribute('cascade_validation', true)
            ->getForm();
        $form->add($this->getForm('firstName'));

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($form->getChildren(), 'Default', 'foo.bar.children', true);

        DelegatingValidationListener::validateFormChildren($form, $context);
    }

    public function testValidateFormChildrenDoesNothingIfDisabled()
    {
        $context = $this->getExecutionContext();
        $graphWalker = $context->getGraphWalker();
        $form = $this->getBuilder()
            ->setAttribute('cascade_validation', false)
            ->getForm();
        $form->add($this->getForm('firstName'));

        $graphWalker->expects($this->never())
            ->method('walkReference');

        DelegatingValidationListener::validateFormChildren($form, $context);
    }

    public function testValidateIgnoresNonRoot()
    {
        $form = $this->getMockForm();
        $form->expects($this->once())
            ->method('isRoot')
            ->will($this->returnValue(false));

        $this->delegate->expects($this->never())
            ->method('validate');

        $this->listener->validateForm(new DataEvent($form, null));
    }
}
