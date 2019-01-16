<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

class PropertyAccessorCollectionTest_Car
{
    private $axes;

    public function __construct($axes = null)
    {
        $this->axes = $axes;
    }

    // In the test, use a name that StringUtil can't uniquely singularify
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

class PropertyAccessorCollectionTest_CarOnlyAdder
{
    public function addAxis($axis)
    {
    }

    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTest_CarOnlyRemover
{
    public function removeAxis($axis)
    {
    }

    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTest_CarNoAdderAndRemover
{
    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTest_CompositeCar
{
    public function getStructure()
    {
    }

    public function setStructure($structure)
    {
    }
}

class PropertyAccessorCollectionTest_CarStructure
{
    public function addAxis($axis)
    {
    }

    public function removeAxis($axis)
    {
    }

    public function getAxes()
    {
    }
}

abstract class PropertyAccessorCollectionTest extends PropertyAccessorArrayAccessTest
{
    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth', 4 => 'fifth']);
        $axesMerged = $this->getContainer([1 => 'first', 2 => 'second', 3 => 'third']);
        $axesAfter = $this->getContainer([1 => 'second', 5 => 'first', 6 => 'third']);
        $axesMergedCopy = \is_object($axesMerged) ? clone $axesMerged : $axesMerged;

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new PropertyAccessorCollectionTest_Car($axesBefore);

        $this->propertyAccessor->setValue($car, 'axes', $axesMerged);

        $this->assertEquals($axesAfter, $car->getAxes());

        // The passed collection was not modified
        $this->assertEquals($axesMergedCopy, $axesMerged);
    }

    public function testSetValueCallsAdderAndRemoverForNestedCollections()
    {
        $car = $this->getMockBuilder(__CLASS__.'_CompositeCar')->getMock();
        $structure = $this->getMockBuilder(__CLASS__.'_CarStructure')->getMock();
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

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

        $this->propertyAccessor->setValue($car, 'structure.axes', $axesAfter);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * @expectedExceptionMessageRegExp /Could not determine access type for property "axes" in class "Mock_PropertyAccessorCollectionTest_CarNoAdderAndRemover_[^"]*"./
     */
    public function testSetValueFailsIfNoAdderNorRemoverFound()
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarNoAdderAndRemover')->getMock();
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

        $car->expects($this->any())
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));

        $this->propertyAccessor->setValue($car, 'axes', $axesAfter);
    }

    public function testIsWritableReturnsTrueIfAdderAndRemoverExists()
    {
        $car = $this->getMockBuilder(__CLASS__.'_Car')->getMock();
        $this->assertTrue($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyAdderExists()
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarOnlyAdder')->getMock();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyRemoverExists()
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarOnlyRemover')->getMock();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfNoAdderNorRemoverExists()
    {
        $car = $this->getMockBuilder(__CLASS__.'_CarNoAdderAndRemover')->getMock();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     * expectedExceptionMessageRegExp /The property "axes" in class "Mock_PropertyAccessorCollectionTest_Car[^"]*" can be defined with the methods "addAxis()", "removeAxis()" but the new value must be an array or an instance of \Traversable, "string" given./
     */
    public function testSetValueFailsIfAdderAndRemoverExistButValueIsNotTraversable()
    {
        $car = $this->getMockBuilder(__CLASS__.'_Car')->getMock();

        $this->propertyAccessor->setValue($car, 'axes', 'Not an array or Traversable');
    }
}
