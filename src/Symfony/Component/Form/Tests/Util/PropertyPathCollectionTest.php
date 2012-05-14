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
    // In the test, use a name that FormUtil can't uniquely singularify
    public function addAxis($axis) {}

    public function removeAxis($axis) {}

    public function getAxes() {}
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

class PropertyPathCollectionTest_RealCar
{
    protected $doors;

    public function addDoor($door) {
        $this->doors[] = $door;
    }

    public function removeDoor($door) {
        foreach ($this->doors as $key => $value) {
            if ($door == $value) {
                unset($this->doors[$key]);
                break;
            }
        }
    }

    public function contains($door) {
        foreach ($this->doors as $key => $value) {
            if ($door == $value) {
                return true;
            }
        }
        return false;
    }

    public function getDoors() {
        return $this->doors;
    }

    public function setDoors($doors) {
        $this->doors = $doors;
    }
}

abstract class PropertyPathCollectionTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function getCollection(array $array);

    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $car = $this->getMock(__CLASS__ . '_Car');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth', 4 => 'fifth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('axes');

        $car->expects($this->at(0))
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));
        $car->expects($this->at(1))
            ->method('removeAxis')
            ->with('fourth');
        $car->expects($this->at(2))
            ->method('removeAxis')
            ->with('fifth');
        $car->expects($this->at(3))
            ->method('addAxis')
            ->with('first');
        $car->expects($this->at(4))
            ->method('addAxis')
            ->with('third');

        $path->setValue($car, $axesAfter);
    }

    public function testSetValueCallsAdderAndRemoverForCollectionsWithObjectAndRealCarBecauseMockSucks()
    {
        $car = new PropertyPathCollectionTest_RealCar();

        $obj1 = (object) array('name' => 'A' . __CLASS__);
        $obj2 = (object) array('name' => 'B' . __CLASS__);
        $obj3 = (object) array('name' => 'C' . __CLASS__);
        $obj4 = (object) array('name' => 'D' . __CLASS__);
        $obj5 = (object) array('name' => 'E' . __CLASS__);

        $car->setDoors($this->getCollection(array(1 => $obj2, 3 => $obj4, 4 => $obj5)));

        $doorsAfter = $this->getCollection(array(0 => $obj1, 1 => $obj2, 2 => $obj3));

        $path = new PropertyPath('doors');
        $path->setValue($car, $doorsAfter);

        $this->assertCount(3, $car->getDoors(), 'the car should have 3 doors');
        $this->assertTrue($car->contains($obj2));
        $this->assertTrue($car->contains($obj1));
        $this->assertTrue($car->contains($obj3));
    }

    public function testSetValueCallsAdderAndRemoverForCollectionsWithStringAndRealCarBecauseMockSucks()
    {
        $car = new PropertyPathCollectionTest_RealCar();
        $car->setDoors($this->getCollection(array(1 => 'second', 3 => 'fourth', 4 => 'fifth')));

        $doorsAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $path = new PropertyPath('doors');
        $path->setValue($car, $doorsAfter);

        $this->assertCount(3, $car->getDoors(), 'the car should have 3 doors');
        $this->assertTrue($car->contains('first'));
        $this->assertTrue($car->contains('second'));
        $this->assertTrue($car->contains('third'));
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
