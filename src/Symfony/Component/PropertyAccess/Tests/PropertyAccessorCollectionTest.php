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

use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\StringUtil;

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

class PropertyAccessorCollectionTest_CarCustomSingular
{
    public function addFoo($axis) {}

    public function removeFoo($axis) {}

    public function getAxes() {}
}

class PropertyAccessorCollectionTest_Engine
{
}

class PropertyAccessorCollectionTest_CarOnlyAdder
{
    public function addAxis($axis) {}

    public function getAxes() {}
}

class PropertyAccessorCollectionTest_CarOnlyRemover
{
    public function removeAxis($axis) {}

    public function getAxes() {}
}

class PropertyAccessorCollectionTest_CarNoAdderAndRemover
{
    public function getAxes() {}
}

class PropertyAccessorCollectionTest_CarNoAdderAndRemoverWithProperty
{
    protected $axes = array();

    public function getAxes() {}
}

class PropertyAccessorCollectionTest_CompositeCar
{
    public function getStructure() {}

    public function setStructure($structure) {}
}

class PropertyAccessorCollectionTest_CarStructure
{
    public function addAxis($axis) {}

    public function removeAxis($axis) {}

    public function getAxes() {}
}

abstract class PropertyAccessorCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    abstract protected function getCollection(array $array);

    public function testGetValueReadsArrayAccess()
    {
        $object = $this->getCollection(array('firstName' => 'Bernhard'));

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($object, '[firstName]'));
    }

    public function testGetValueReadsNestedArrayAccess()
    {
        $object = $this->getCollection(array('person' => array('firstName' => 'Bernhard')));

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($object, '[person][firstName]'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfArrayAccessExpected()
    {
        $this->propertyAccessor->getValue(new \stdClass(), '[firstName]');
    }

    public function testSetValueUpdatesArrayAccess()
    {
        $object = $this->getCollection(array());

        $this->propertyAccessor->setValue($object, '[firstName]', 'Bernhard');

        $this->assertEquals('Bernhard', $object['firstName']);
    }

    public function testSetValueUpdatesNestedArrayAccess()
    {
        $object = $this->getCollection(array());

        $this->propertyAccessor->setValue($object, '[person][firstName]', 'Bernhard');

        $this->assertEquals('Bernhard', $object['person']['firstName']);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfArrayAccessExpected()
    {
        $this->propertyAccessor->setValue(new \stdClass(), '[firstName]', 'Bernhard');
    }

    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth', 4 => 'fifth'));
        $axesMerged = $this->getCollection(array(1 => 'first', 2 => 'second', 3 => 'third'));
        $axesAfter = $this->getCollection(array(1 => 'second', 5 => 'first', 6 => 'third'));
        $axesMergedCopy = is_object($axesMerged) ? clone $axesMerged : $axesMerged;

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
        $car = $this->getMock(__CLASS__ . '_CompositeCar');
        $structure = $this->getMock(__CLASS__ . '_CarStructure');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

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

    public function testSetValueCallsCustomAdderAndRemover()
    {
        $this->markTestSkipped('This feature is temporarily disabled as of 2.1');

        $car = $this->getMock(__CLASS__ . '_CarCustomSingular');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

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

        $this->propertyAccessor->setValue($car, 'axes|foo', $axesAfter);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueFailsIfOnlyAdderFound()
    {
        $car = $this->getMock(__CLASS__ . '_CarOnlyAdder');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $car->expects($this->any())
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));

        $this->propertyAccessor->setValue($car, 'axes', $axesAfter);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueFailsIfOnlyRemoverFound()
    {
        $car = $this->getMock(__CLASS__ . '_CarOnlyRemover');
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth'));
        $axesAfter = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        $car->expects($this->any())
            ->method('getAxes')
            ->will($this->returnValue($axesBefore));

        $this->propertyAccessor->setValue($car, 'axes', $axesAfter);
    }

    /**
     * @dataProvider noAdderRemoverData
     */
    public function testNoAdderAndRemoverThrowsSensibleError($car, $path, $message)
    {
        $axes = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        try {
            $this->propertyAccessor->setValue($car, $path, $axes);
            $this->fail('An expected exception was not thrown!');
        } catch (ExceptionInterface $e) {
            $this->assertEquals($message, $e->getMessage());
        }
    }

    public function noAdderRemoverData()
    {
        $data = array();

        $car = $this->getMock(__CLASS__ . '_CarNoAdderAndRemover');
        $propertyPath = 'axes';
        $expectedMessage = sprintf(
            'Neither element "axes" nor method "setAxes()" exists in class '
                .'"%s", nor could adders and removers be found based on the '
                .'guessed singulars: %s'
//                . '(provide a singular by suffixing the '
//                .'property path with "|{singular}" to override the guesser)'
                ,
            get_class($car),
            implode(', ', (array) $singulars = StringUtil::singularify('Axes'))
        );
        $data[] = array($car, $propertyPath, $expectedMessage);

        /*
        Temporarily disabled in 2.1

        $propertyPath = new PropertyPath('axes|boo');
        $expectedMessage = sprintf(
            'Neither element "axes" nor method "setAxes()" exists in class '
                .'"%s", nor could adders and removers be found based on the '
                .'passed singular: %s',
            get_class($car),
            'boo'
        );
        $data[] = array($car, $propertyPath, $expectedMessage);
         */

        $car = $this->getMock(__CLASS__ . '_CarNoAdderAndRemoverWithProperty');
        $propertyPath = 'axes';
        $expectedMessage = sprintf(
            'Property "axes" is not public in class "%s", nor could adders and '
                .'removers be found based on the guessed singulars: %s'
//                .' (provide a singular by suffixing the property path with '
//                .'"|{singular}" to override the guesser)'
                . '. Maybe you should '
                .'create the method "setAxes()"?',
            get_class($car),
            implode(', ', (array) $singulars = StringUtil::singularify('Axes'))
        );
        $data[] = array($car, $propertyPath, $expectedMessage);

        return $data;
    }
}
