<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use Symfony\Component\Form\Util\PropertyPath;

class PropertyPathCollectionTest_Car
{
    private $axes;

    public function __construct($axes = null)
    {
        $this->axes = $axes;
    }

    // In the test, use a name that FormUtil can't uniquely singularify
    public function addAxis($axis)
    {
        $this->axes[] = $axis;
    }

    public function removeAxis($axis)
    {
        foreach ($this->axes as $key => $value) {
            if ($value === $axis) {
                unset($this->axes[$key]);

                return;
            }
        }
    }

    public function getAxes()
    {
        return $this->axes;
    }
}

class PropertyPathCollectionTest_CarCustomSingular
{
    public function addFoo($axis) {}

    public function removeFoo($axis) {}

    public function getAxes() {}
}

class PropertyPathCollectionTest_Engine
{
}

class PropertyPathCollectionTest_CarOnlyAdder
{
    public function addAxis($axis) {}

    public function getAxes() {}
}

class PropertyPathCollectionTest_CarOnlyRemover
{
    public function removeAxis($axis) {}

    public function getAxes() {}
}

class PropertyPathCollectionTest_CompositeCar
{
    public function getStructure() {}
}

class PropertyPathCollectionTest_CarStructure
{
    public function addAxis($axis) {}

    public function removeAxis($axis) {}

    public function getAxes() {}
}

abstract class PropertyPathCollectionTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function getCollection(array $array);

    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth', 4 => 'fifth'));
        $axesMerged = $this->getCollection(array(1 => 'first', 2 => 'second', 3 => 'third'));
        $axesAfter = $this->getCollection(array(1 => 'second', 5 => 'first', 6 => 'third'));

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new PropertyPathCollectionTest_Car($axesBefore);

        $path = new PropertyPath('axes');

        $path->setValue($car, $axesMerged);

        $this->assertEquals($axesAfter, $car->getAxes());
    }

    public function testSetValueCallsAdderAndRemoverForNestedCollections()
    {
        $car = $this->getMock(__CLASS__ . '_CompositeCar');
        $structure = $this->getMock(__CLASS__ . '_CarStructure');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('structure.axes');

        $car->expects($this->any())
            ->method('getStructure')
            ->will($this->returnValue($structure));

        $structure->expects($this->at(0))
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));
        $structure->expects($this->at(1))
            ->method('removeAxis')
            ->with('fourth');
        $structure->expects($this->at(2))
            ->method('addAxis')
            ->with('first');
        $structure->expects($this->at(3))
            ->method('addAxis')
            ->with('third');

        $path->setValue($car, $axesAfter);
    }

    public function testSetValueCallsCustomAdderAndRemover()
    {
        $car = $this->getMock(__CLASS__ . '_CarCustomSingular');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('axes|foo');

        $car->expects($this->at(0))
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));
        $car->expects($this->at(1))
            ->method('removeFoo')
            ->with('fourth');
        $car->expects($this->at(2))
            ->method('addFoo')
            ->with('first');
        $car->expects($this->at(3))
            ->method('addFoo')
            ->with('third');

        $path->setValue($car, $axesAfter);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testMapFormToDataFailsIfOnlyAdderFound()
    {
        $car = $this->getMock(__CLASS__ . '_CarOnlyAdder');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('axes');

        $car->expects($this->any())
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));

        $path->setValue($car, $axesAfter);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testMapFormToDataFailsIfOnlyRemoverFound()
    {
        $car = $this->getMock(__CLASS__ . '_CarOnlyRemover');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('axes');

        $car->expects($this->any())
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));

        $path->setValue($car, $axesAfter);
    }
}
