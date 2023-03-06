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

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

class PropertyAccessorCollectionTestCase_Car
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

class PropertyAccessorCollectionTestCase_CarOnlyAdder
{
    public function addAxis($axis)
    {
    }

    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTestCase_CarOnlyRemover
{
    public function removeAxis($axis)
    {
    }

    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTestCase_CarNoAdderAndRemover
{
    public function getAxes()
    {
    }
}

class PropertyAccessorCollectionTestCase_CompositeCar
{
    public function getStructure()
    {
    }

    public function setStructure($structure)
    {
    }
}

class PropertyAccessorCollectionTestCase_CarStructure
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

abstract class PropertyAccessorCollectionTestCase extends PropertyAccessorArrayAccessTestCase
{
    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth', 4 => 'fifth']);
        $axesMerged = $this->getContainer([1 => 'first', 2 => 'second', 3 => 'third']);
        $axesAfter = $this->getContainer([1 => 'second', 5 => 'first', 6 => 'third']);
        $axesMergedCopy = \is_object($axesMerged) ? clone $axesMerged : $axesMerged;

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new PropertyAccessorCollectionTestCase_Car($axesBefore);

        $this->propertyAccessor->setValue($car, 'axes', $axesMerged);

        $this->assertEquals($axesAfter, $car->getAxes());

        // The passed collection was not modified
        $this->assertEquals($axesMergedCopy, $axesMerged);
    }

    public function testSetValueCallsAdderAndRemoverForNestedCollections()
    {
        $car = $this->createMock(__CLASS__.'_CompositeCar');
        $structure = $this->createMock(__CLASS__.'_CarStructure');
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

        $car->expects($this->any())
            ->method('getStructure')
            ->willReturn($structure);

        $structure->expects($this->once())
            ->method('getAxes')
            ->willReturn($axesBefore);
        $structure->expects($this->once())
            ->method('removeAxis')
            ->with('fourth');

        $structure->expects($this->exactly(2))
            ->method('addAxis')
            ->willReturnCallback(function (string $axis) {
                static $series = [
                    'first',
                    'third',
                ];

                $this->assertSame(array_shift($series), $axis);
            })
        ;

        $this->propertyAccessor->setValue($car, 'structure.axes', $axesAfter);
    }

    public function testSetValueFailsIfNoAdderNorRemoverFound()
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->expectExceptionMessageMatches('/Could not determine access type for property "axes" in class "Mock_PropertyAccessorCollectionTestCase_CarNoAdderAndRemover_[^"]*"./');
        $car = $this->createMock(__CLASS__.'_CarNoAdderAndRemover');
        $axesBefore = $this->getContainer([1 => 'second', 3 => 'fourth']);
        $axesAfter = $this->getContainer([0 => 'first', 1 => 'second', 2 => 'third']);

        $car->expects($this->any())
            ->method('getAxes')
            ->willReturn($axesBefore);

        $this->propertyAccessor->setValue($car, 'axes', $axesAfter);
    }

    public function testIsWritableReturnsTrueIfAdderAndRemoverExists()
    {
        $car = new PropertyAccessorCollectionTestCase_Car();
        $this->assertTrue($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyAdderExists()
    {
        $car = new PropertyAccessorCollectionTestCase_CarOnlyAdder();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfOnlyRemoverExists()
    {
        $car = new PropertyAccessorCollectionTestCase_CarOnlyRemover();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testIsWritableReturnsFalseIfNoAdderNorRemoverExists()
    {
        $car = new PropertyAccessorCollectionTestCase_CarNoAdderAndRemover();
        $this->assertFalse($this->propertyAccessor->isWritable($car, 'axes'));
    }

    public function testSetValueFailsIfAdderAndRemoverExistButValueIsNotTraversable()
    {
        $this->expectException(NoSuchPropertyException::class);
        $this->expectExceptionMessageMatches('/The property "axes" in class "Symfony\\\Component\\\PropertyAccess\\\Tests\\\PropertyAccessorCollectionTestCase_Car" can be defined with the methods "addAxis\(\)", "removeAxis\(\)" but the new value must be an array or an instance of \\\Traversable\./');
        $car = new PropertyAccessorCollectionTestCase_Car();

        $this->propertyAccessor->setValue($car, 'axes', 'Not an array or Traversable');
    }
}
