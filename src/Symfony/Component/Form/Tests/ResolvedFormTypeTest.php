<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResolvedFormTypeTest extends \PHPUnit_Framework_TestCase
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
    private $dataMapper;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\OptionsResolver\OptionsResolver')) {
            $this->markTestSkipped('The "OptionsResolver" component is not available');
        }

        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->dataMapper = $this->getMock('Symfony\Component\Form\DataMapperInterface');
    }

    public function testCreateBuilder()
    {
        if (version_compare(\PHPUnit_Runner_Version::id(), '3.7', '<')) {
            $this->markTestSkipped('This test requires PHPUnit 3.7.');
        }

        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $extension1 = $this->getMockFormTypeExtension();
        $extension2 = $this->getMockFormTypeExtension();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array($extension1, $extension2), $parentResolvedType);

        $test = $this;
        $i = 0;

        $assertIndex = function ($index) use (&$i, $test) {
            return function () use (&$i, $test, $index) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->assertEquals($index, $i, 'Executed at index '.$index);

                ++$i;
            };
        };

        $assertIndexAndAddOption = function ($index, $option, $default) use ($assertIndex) {
            $assertIndex = $assertIndex($index);

            return function (OptionsResolverInterface $resolver) use ($assertIndex, $index, $option, $default) {
                $assertIndex();

                $resolver->setDefaults(array($option => $default));
            };
        };

        // First the default options are generated for the super type
        $parentType->expects($this->once())
            ->method('setDefaultOptions')
            ->will($this->returnCallback($assertIndexAndAddOption(0, 'a', 'a_default')));

        // The form type itself
        $type->expects($this->once())
            ->method('setDefaultOptions')
            ->will($this->returnCallback($assertIndexAndAddOption(1, 'b', 'b_default')));

        // And its extensions
        $extension1->expects($this->once())
            ->method('setDefaultOptions')
            ->will($this->returnCallback($assertIndexAndAddOption(2, 'c', 'c_default')));

        $extension2->expects($this->once())
            ->method('setDefaultOptions')
            ->will($this->returnCallback($assertIndexAndAddOption(3, 'd', 'd_default')));

        $givenOptions = array('a' => 'a_custom', 'c' => 'c_custom');
        $resolvedOptions = array('a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default');

        // Then the form is built for the super type
        $parentType->expects($this->once())
            ->method('buildForm')
            ->with($this->anything(), $resolvedOptions)
            ->will($this->returnCallback($assertIndex(4)));

        // Then the type itself
        $type->expects($this->once())
            ->method('buildForm')
            ->with($this->anything(), $resolvedOptions)
            ->will($this->returnCallback($assertIndex(5)));

        // Then its extensions
        $extension1->expects($this->once())
            ->method('buildForm')
            ->with($this->anything(), $resolvedOptions)
            ->will($this->returnCallback($assertIndex(6)));

        $extension2->expects($this->once())
            ->method('buildForm')
            ->with($this->anything(), $resolvedOptions)
            ->will($this->returnCallback($assertIndex(7)));

        $factory = $this->getMockFormFactory();
        $builder = $resolvedType->createBuilder($factory, 'name', $givenOptions);

        $this->assertSame($resolvedType, $builder->getType());
    }

    public function testCreateViewWithoutOrder()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $extension1 = $this->getMockFormTypeExtension();
        $extension2 = $this->getMockFormTypeExtension();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array($extension1, $extension2), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);

        $options = array('a' => '1', 'b' => '2');
        $form = $this->getBuilder('name', $options)
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->getForm();

        $test = $this;
        $i = 0;

        $assertIndexAndNbOfChildViews = function ($index, $nbOfChildViews) use (&$i, $test) {
            return function (FormView $view) use (&$i, $test, $index, $nbOfChildViews) {
                /* @var \PHPUnit_Framework_TestCase $test */
                $test->assertEquals($index, $i, 'Executed at index '.$index);
                $test->assertCount($nbOfChildViews, $view);

                ++$i;
            };
        };

        // First the super type
        $parentType->expects($this->once())
            ->method('buildView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(0, 0)));

        // Then the type itself
        $type->expects($this->once())
            ->method('buildView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(1, 0)));

        // Then its extensions
        $extension1->expects($this->once())
            ->method('buildView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(2, 0)));

        $extension2->expects($this->once())
            ->method('buildView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(3, 0)));

        // Now the first child form
        $field1Type->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(4, 0)));
        $field1Type->expects($this->once())
            ->method('finishView')
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(5, 0)));

        // And the second child form
        $field2Type->expects($this->once())
            ->method('buildView')
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(6, 0)));
        $field2Type->expects($this->once())
            ->method('finishView')
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(7, 0)));

        // Again first the parent
        $parentType->expects($this->once())
            ->method('finishView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(8, 2)));

        // Then the type itself
        $type->expects($this->once())
            ->method('finishView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(9, 2)));

        // Then its extensions
        $extension1->expects($this->once())
            ->method('finishView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(10, 2)));

        $extension2->expects($this->once())
            ->method('finishView')
            ->with($this->anything(), $form, $options)
            ->will($this->returnCallback($assertIndexAndNbOfChildViews(11, 2)));

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $this->assertSame($parentView, $view->parent);
    }

    public function testCreateViewOrderWithSimpleBeforePlacedAfterTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bat']);
    }

    public function testCreateViewOrderWithSimpleBeforePlacedBeforeTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('before' => 'bat')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bat']);
    }

    public function testCreateViewOrderWithMultipleBeforePlacedAfterTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bat']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bar']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['ban']);
    }

    public function testCreateViewOrderWithMultipleBeforePlacedBeforeTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('before' => 'ban')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('before' => 'ban')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['bat']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['baz']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['ban']);
    }

    public function testCreateViewOrderWithSimpleAfterPlacedBeforeTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('after' => 'baz')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bat']);
    }

    public function testCreateViewOrderWithSimpleAfterPlacedAfterTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('after' => 'foo')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bat']);
    }

    public function testCreateViewOrderWithMultipleAfterPlacedBeforeTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('after' => 'bat')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('after' => 'bat')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['bat']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['baz']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['ban']);
    }

    public function testCreateViewOrderWithMultipleAfterPlacedAfterTheReferencedForm()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('after' => 'foo')))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType)->setPosition(array('after' => 'foo')))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['foo']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bat']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['bar']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['ban']);
    }

    public function testCreateViewWithMultipleBeforeAndAfter()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();
        $field6Type = $this->getMockFormType();
        $field7Type = $this->getMockFormType();
        $field8Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);
        $field6ResolvedType = new ResolvedFormType($field6Type);
        $field7ResolvedType = new ResolvedFormType($field7Type);
        $field8ResolvedType = new ResolvedFormType($field8Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('start')->setType($field1ResolvedType))
            ->add($this->getBuilder('foo')->setType($field2ResolvedType)->setPosition(array('after' => 'baz')))
            ->add($this->getBuilder('bar')->setType($field3ResolvedType)->setPosition(array('before' => 'bat')))
            ->add($this->getBuilder('baz')->setType($field4ResolvedType))
            ->add($this->getBuilder('bat')->setType($field5ResolvedType))
            ->add($this->getBuilder('ban')->setType($field6ResolvedType)->setPosition(array('after' => 'foo')))
            ->add($this->getBuilder('baw')->setType($field7ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('end')->setType($field8ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['start']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['foo']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['ban']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['baw']);

        $this->assertArrayHasKey(5, $children);
        $this->assertSame($children[5], $view->children['bar']);

        $this->assertArrayHasKey(6, $children);
        $this->assertSame($children[6], $view->children['bat']);

        $this->assertArrayHasKey(7, $children);
        $this->assertSame($children[7], $view->children['end']);
    }

    public function testCreateViewOrderWithSimpleFirst()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition('first'))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['bar']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['foo']);
    }

    public function testCreateViewOrderWithMultipleFirst()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition('first'))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition('first'))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['bar']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['baz']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['foo']);
    }

    public function testCreateViewOrderWithSimpleLast()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['bar']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['foo']);
    }

    public function testCreateViewOrderWithMultipleLast()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['baz']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['foo']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bar']);
    }

    public function testCreateViewOrderWithSimpleFirstAndLast()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType)->setPosition('first'))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['bat']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['foo']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['baz']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['ban']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['bar']);
    }

    public function testCreateViewOrderWithMultipleFirstAndLast()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition('last'))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType))
            ->add($this->getBuilder('bat')->setType($field4ResolvedType)->setPosition('first'))
            ->add($this->getBuilder('ban')->setType($field5ResolvedType)->setPosition('first'))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['bat']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['ban']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['baz']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['foo']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['bar']);
    }

    public function testCreateViewWithMultipleFirstAndLastAndBeforeAndAfter()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();
        $field4Type = $this->getMockFormType();
        $field5Type = $this->getMockFormType();
        $field6Type = $this->getMockFormType();
        $field7Type = $this->getMockFormType();
        $field8Type = $this->getMockFormType();
        $field9Type = $this->getMockFormType();
        $field10Type = $this->getMockFormType();
        $field11Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);
        $field4ResolvedType = new ResolvedFormType($field4Type);
        $field5ResolvedType = new ResolvedFormType($field5Type);
        $field6ResolvedType = new ResolvedFormType($field6Type);
        $field7ResolvedType = new ResolvedFormType($field7Type);
        $field8ResolvedType = new ResolvedFormType($field8Type);
        $field9ResolvedType = new ResolvedFormType($field9Type);
        $field10ResolvedType = new ResolvedFormType($field10Type);
        $field11ResolvedType = new ResolvedFormType($field11Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('bri')->setType($field1ResolvedType)->setPosition(array('after' => 'bro')))
            ->add($this->getBuilder('foo')->setType($field2ResolvedType)->setPosition(array('after' => 'baz')))
            ->add($this->getBuilder('bar')->setType($field3ResolvedType)->setPosition(array('before' => 'bat')))
            ->add($this->getBuilder('baz')->setType($field4ResolvedType))
            ->add($this->getBuilder('pop')->setType($field5ResolvedType)->setPosition(array('before' => 'bro')))
            ->add($this->getBuilder('psy')->setType($field7ResolvedType)->setPosition(array('after' => 'bat')))
            ->add($this->getBuilder('bat')->setType($field6ResolvedType)->setPosition('first'))
            ->add($this->getBuilder('ban')->setType($field8ResolvedType)->setPosition(array('after' => 'foo')))
            ->add($this->getBuilder('raz')->setType($field9ResolvedType)->setPosition('first'))
            ->add($this->getBuilder('baw')->setType($field10ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('bro')->setType($field11ResolvedType)->setPosition('last'))
            ->getForm();

        $parentView = new FormView();
        $view = $resolvedType->createView($form, $parentView);

        $children = array_values($view->children);

        $this->assertArrayHasKey(0, $children);
        $this->assertSame($children[0], $view->children['baw']);

        $this->assertArrayHasKey(1, $children);
        $this->assertSame($children[1], $view->children['bar']);

        $this->assertArrayHasKey(2, $children);
        $this->assertSame($children[2], $view->children['bat']);

        $this->assertArrayHasKey(3, $children);
        $this->assertSame($children[3], $view->children['psy']);

        $this->assertArrayHasKey(4, $children);
        $this->assertSame($children[4], $view->children['raz']);

        $this->assertArrayHasKey(5, $children);
        $this->assertSame($children[5], $view->children['baz']);

        $this->assertArrayHasKey(6, $children);
        $this->assertSame($children[6], $view->children['foo']);

        $this->assertArrayHasKey(7, $children);
        $this->assertSame($children[7], $view->children['ban']);

        $this->assertArrayHasKey(8, $children);
        $this->assertSame($children[8], $view->children['pop']);

        $this->assertArrayHasKey(9, $children);
        $this->assertSame($children[9], $view->children['bro']);

        $this->assertArrayHasKey(10, $children);
        $this->assertSame($children[10], $view->children['bri']);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidConfigurationException
     */
    public function testCreateViewWithInvalidStringPosition()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $fieldType = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($fieldType);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition('foo'))
            ->getForm();

        $parentView = new FormView();
        $resolvedType->createView($form, $parentView);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidConfigurationException
     */
    public function testCreateViewWithInvalidBeforeOrder()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition(array('before' => 'bar')))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('before' => 'baz')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('before' => 'foo')))
            ->getForm();

        $parentView = new FormView();
        $resolvedType->createView($form, $parentView);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidConfigurationException
     */
    public function testCreateViewWithInvalidAfterOrder()
    {
        $parentType = $this->getMockFormType();
        $type = $this->getMockFormType();
        $field1Type = $this->getMockFormType();
        $field2Type = $this->getMockFormType();
        $field3Type = $this->getMockFormType();

        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type, array(), $parentResolvedType);
        $field1ResolvedType = new ResolvedFormType($field1Type);
        $field2ResolvedType = new ResolvedFormType($field2Type);
        $field3ResolvedType = new ResolvedFormType($field3Type);

        $form = $this->getBuilder('name')
            ->setCompound(true)
            ->setDataMapper($this->dataMapper)
            ->setType($resolvedType)
            ->add($this->getBuilder('foo')->setType($field1ResolvedType)->setPosition(array('after' => 'bar')))
            ->add($this->getBuilder('bar')->setType($field2ResolvedType)->setPosition(array('after' => 'baz')))
            ->add($this->getBuilder('baz')->setType($field3ResolvedType)->setPosition(array('after' => 'foo')))
            ->getForm();

        $parentView = new FormView();
        $resolvedType->createView($form, $parentView);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockFormType()
    {
        return $this->getMock('Symfony\Component\Form\FormTypeInterface');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockFormTypeExtension()
    {
        return $this->getMock('Symfony\Component\Form\FormTypeExtensionInterface');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockFormFactory()
    {
        return $this->getMock('Symfony\Component\Form\FormFactoryInterface');
    }

    /**
     * @param string $name
     * @param array $options
     *
     * @return FormBuilder
     */
    protected function getBuilder($name = 'name', array $options = array())
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory, $options);
    }
}
