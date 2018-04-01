<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\FormBuilder;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractFormTest extends TestCase
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var \Symphony\Component\Form\FormFactoryInterface
     */
    protected $factory;

    /**
     * @var \Symphony\Component\Form\FormInterface
     */
    protected $form;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->getMockBuilder('Symphony\Component\Form\FormFactoryInterface')->getMock();
        $this->form = $this->createForm();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->form = null;
    }

    /**
     * @return \Symphony\Component\Form\FormInterface
     */
    abstract protected function createForm();

    /**
     * @param string                   $name
     * @param EventDispatcherInterface $dispatcher
     * @param string                   $dataClass
     * @param array                    $options
     *
     * @return FormBuilder
     */
    protected function getBuilder($name = 'name', EventDispatcherInterface $dispatcher = null, $dataClass = null, array $options = array())
    {
        return new FormBuilder($name, $dataClass, $dispatcher ?: $this->dispatcher, $this->factory, $options);
    }

    /**
     * @param string $name
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockForm($name = 'name')
    {
        $form = $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
        $config = $this->getMockBuilder('Symphony\Component\Form\FormConfigInterface')->getMock();

        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        $form->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($config));

        return $form;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDataMapper()
    {
        return $this->getMockBuilder('Symphony\Component\Form\DataMapperInterface')->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDataTransformer()
    {
        return $this->getMockBuilder('Symphony\Component\Form\DataTransformerInterface')->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getFormValidator()
    {
        return $this->getMockBuilder('Symphony\Component\Form\FormValidatorInterface')->getMock();
    }
}
