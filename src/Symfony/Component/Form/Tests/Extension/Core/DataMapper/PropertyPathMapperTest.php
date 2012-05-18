<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataMapper;

use Symfony\Component\Form\Tests\FormInterface;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;

abstract class PropertyPathMapperTest_Form implements FormInterface
{
    private $attributes = array();

    private $data;

    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}

class PropertyPathMapperTest extends \PHPUnit_Framework_TestCase
{
    private $mapper;

    protected function setUp()
    {
        $this->mapper = new PropertyPathMapper();
    }

    protected function tearDown()
    {
        $this->mapper = null;
    }

    private function getPropertyPath($path)
    {
        return $this->getMockBuilder('Symfony\Component\Form\Util\PropertyPath')
            ->setConstructorArgs(array($path))
            ->setMethods(array('getValue', 'setValue'))
            ->getMock();
    }

    private function getForm(PropertyPath $propertyPath = null, $byReference, $synchronized = true, $mapped = true, $disabled = false)
    {
        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');

        $config->expects($this->any())
            ->method('getMapped')
            ->will($this->returnValue($mapped));

        $form = $this->getMockBuilder(__CLASS__ . '_Form')
            // PHPUnit's getMockForAbstractClass does not behave like in the docs..
            // If the array is empty, all methods are mocked. If it is not
            // empty, only abstract methods and the methods in the array are
            // mocked.
            ->setMethods(array('foo'))
            ->getMockForAbstractClass();

        $form->setAttribute('by_reference', $byReference);

        $form->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($config));

        $form->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue($propertyPath));

        $form->expects($this->any())
            ->method('isSynchronized')
            ->will($this->returnValue($synchronized));

        $form->expects($this->any())
            ->method('isDisabled')
            ->will($this->returnValue($disabled));

        return $form;
    }

    public function testMapDataToFormPassesObjectRefIfByReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->once())
            ->method('getValue')
            ->with($car)
            ->will($this->returnValue($engine));

        $form = $this->getForm($propertyPath, true);

        $this->mapper->mapDataToForm($car, $form);

        // Can't use isIdentical() above because mocks always clone their
        // arguments which can't be disabled in PHPUnit 3.6
        $this->assertSame($engine, $form->getData());
    }

    public function testMapDataToFormPassesObjectCloneIfNotByReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->once())
            ->method('getValue')
            ->with($car)
            ->will($this->returnValue($engine));

        $form = $this->getForm($propertyPath, false);

        $this->mapper->mapDataToForm($car, $form);

        $this->assertNotSame($engine, $form->getData());
        $this->assertEquals($engine, $form->getData());
    }

    public function testMapDataToFormIgnoresEmptyPropertyPath()
    {
        $car = new \stdClass();

        $form = $this->getForm(null, true);

        $this->mapper->mapDataToForm($car, $form);

        $this->assertNull($form->getData());
    }

    public function testMapDataToFormIgnoresUnmapped()
    {
        $car = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->never())
            ->method('getValue');

        $form = $this->getForm($propertyPath, true, true, false);

        $this->mapper->mapDataToForm($car, $form);

        $this->assertNull($form->getData());
    }

    public function testMapDataToFormIgnoresEmptyData()
    {
        $propertyPath = $this->getPropertyPath('engine');
        $form = $this->getForm($propertyPath, true);

        $this->mapper->mapDataToForm(null, $form);

        $this->assertNull($form->getData());
    }

    public function testMapFormToDataWritesBackIfNotByReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->once())
            ->method('setValue')
            ->with($car, $engine);

        $form = $this->getForm($propertyPath, false);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataWritesBackIfByReferenceButNoReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->once())
            ->method('setValue')
            ->with($car, $engine);

        $form = $this->getForm($propertyPath, true);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataWritesBackIfByReferenceAndReference()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        // $car already contains the reference of $engine
        $propertyPath->expects($this->once())
            ->method('getValue')
            ->with($car)
            ->will($this->returnValue($engine));

        $propertyPath->expects($this->never())
            ->method('setValue');

        $form = $this->getForm($propertyPath, true);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataIgnoresUnmapped()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->never())
            ->method('setValue');

        $form = $this->getForm($propertyPath, true, true, false);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataIgnoresEmptyData()
    {
        $car = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->never())
            ->method('setValue');

        $form = $this->getForm($propertyPath, true);
        $form->setData(null);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataIgnoresUnsynchronized()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->never())
            ->method('setValue');

        $form = $this->getForm($propertyPath, true, false);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }

    public function testMapFormToDataIgnoresDisabled()
    {
        $car = new \stdClass();
        $engine = new \stdClass();
        $propertyPath = $this->getPropertyPath('engine');

        $propertyPath->expects($this->never())
            ->method('setValue');

        $form = $this->getForm($propertyPath, true, true, true, true);
        $form->setData($engine);

        $this->mapper->mapFormToData($form, $car);
    }
}
