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
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\Form;
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
                $test->assertEquals($index, $i, 'Executed at index ' . $index);

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

        // Can only be uncommented when the following PHPUnit "bug" is fixed:
        // https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47
        // $givenOptions = array('a' => 'a_custom', 'c' => 'c_custom');
        // $resolvedOptions = array('a' => 'a_custom', 'b' => 'b_default', 'c' => 'c_custom', 'd' => 'd_default');

        $givenOptions = array();
        $resolvedOptions = array();

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
        $parentBuilder = $this->getBuilder('parent');
        $builder = $resolvedType->createBuilder($factory, 'name', $givenOptions, $parentBuilder);

        $this->assertSame($parentBuilder, $builder->getParent());
        $this->assertSame($resolvedType, $builder->getType());
    }

    public function testCreateView()
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
                $test->assertEquals($index, $i, 'Executed at index ' . $index);
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
