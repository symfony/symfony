<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\DataMapper;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;

class PropertyPathMapperTest extends \PHPUnit_Framework_TestCase
{
    private $mapper;

    private $propertyPath;

    protected function setUp()
    {
        $this->mapper = new PropertyPathMapper();
        $this->propertyPath = $this->getMockBuilder('Symfony\Component\Form\Util\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        $this->mapper = null;
        $this->propertyPath = null;
    }

    private function getForm(PropertyPath $propertyPath = null)
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $form->expects($this->any())
            ->method('getAttribute')
            ->with('property_path')
            ->will($this->returnValue($propertyPath));

        return $form;
    }

    public function testMapDataToForm()
    {
        $data = new \stdClass();

        $this->propertyPath->expects($this->once())
            ->method('getValue')
            ->with($data)
            ->will($this->returnValue('foobar'));

        $form = $this->getForm($this->propertyPath);

        $form->expects($this->once())
            ->method('setData')
            ->with('foobar');

        $this->mapper->mapDataToForm($data, $form);
    }

    public function testMapDataToFormIgnoresEmptyPropertyPath()
    {
        $data = new \stdClass();

        $form = $this->getForm(null);

        $form->expects($this->never())
            ->method('setData');

        $this->mapper->mapDataToForm($data, $form);
    }

    public function testMapDataToFormIgnoresEmptyData()
    {
        $form = $this->getForm($this->propertyPath);

        $form->expects($this->never())
            ->method('setData');

        $form->getAttribute('property_path'); // <- weird PHPUnit bug if I don't do this

        $this->mapper->mapDataToForm(null, $form);
    }
}
