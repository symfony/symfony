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
use Symfony\Component\Form\Util\FormUtil;

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

class PropertyPathCollectionTest_CarNoAdderAndRemover
{
    public function getAxes() {}
}

class PropertyPathCollectionTest_CarNoAdderAndRemoverWithProperty
{
    protected $axes = array();

    public function getAxes() {}
}

class PropertyPathCollectionTest_CompositeCar
{
    public function getStructure() {}

    public function setStructure($structure) {}
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

    public function testGetValueReadsArrayAccess()
    {
        $object = $this->getCollection(array('firstName' => 'Bernhard'));

        $path = new PropertyPath('[firstName]');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    public function testGetValueReadsNestedArrayAccess()
    {
        $object = $this->getCollection(array('person' => array('firstName' => 'Bernhard')));

        $path = new PropertyPath('[person][firstName]');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testGetValueThrowsExceptionIfArrayAccessExpected()
    {
        $path = new PropertyPath('[firstName]');

        $path->getValue(new \stdClass());
    }

    public function testSetValueUpdatesArrayAccess()
    {
        $object = $this->getCollection(array());

        $path = new PropertyPath('[firstName]');
        $path->setValue($object, 'Bernhard');

        $this->assertEquals('Bernhard', $object['firstName']);
    }

    public function testSetValueUpdatesNestedArrayAccess()
    {
        $object = $this->getCollection(array());

        $path = new PropertyPath('[person][firstName]');
        $path->setValue($object, 'Bernhard');

        $this->assertEquals('Bernhard', $object['person']['firstName']);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testSetValueThrowsExceptionIfArrayAccessExpected()
    {
        $path = new PropertyPath('[firstName]');

        $path->setValue(new \stdClass(), 'Bernhard');
    }

    public function testSetValueCallsAdderAndRemoverForCollections()
    {
        $axesBefore = $this->getCollection(array(1 => 'second', 3 => 'fourth', 4 => 'fifth'));
        $axesMerged = $this->getCollection(array(1 => 'first', 2 => 'second', 3 => 'third'));
        $axesAfter = $this->getCollection(array(1 => 'second', 5 => 'first', 6 => 'third'));
        $axesMergedCopy = is_object($axesMerged) ? clone $axesMerged : $axesMerged;

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new PropertyPathCollectionTest_Car($axesBefore);

        $path = new PropertyPath('axes');

        $path->setValue($car, $axesMerged);

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
        $this->markTestSkipped('This feature is temporarily disabled as of 2.1');

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

    /**
     * @dataProvider noAdderRemoverData
     */
    public function testNoAdderAndRemoverThrowsSensibleError($car, $path, $message)
    {
        $axes = $this->getCollection(array(0 => 'first', 1 => 'second', 2 => 'third'));

        try {
            $path->setValue($car, $axes);
            $this->fail('An expected exception was not thrown!');
        } catch (\Symfony\Component\Form\Exception\FormException $e) {
            $this->assertEquals($message, $e->getMessage());
        }
    }

    public function noAdderRemoverData()
    {
        $data = array();

        $car = $this->getMock(__CLASS__ . '_CarNoAdderAndRemover');
        $propertyPath = new PropertyPath('axes');
        $expectedMessage = sprintf(
            'Neither element "axes" nor method "setAxes()" exists in class '
                .'"%s", nor could adders and removers be found based on the '
                .'guessed singulars: %s'
//                . '(provide a singular by suffixing the '
//                .'property path with "|{singular}" to override the guesser)'
                ,
            get_class($car),
            implode(', ', (array) $singulars = FormUtil::singularify('Axes'))
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
        $propertyPath = new PropertyPath('axes');
        $expectedMessage = sprintf(
            'Property "axes" is not public in class "%s", nor could adders and '
                .'removers be found based on the guessed singulars: %s'
//                .' (provide a singular by suffixing the property path with '
//                .'"|{singular}" to override the guesser)'
                . '. Maybe you should '
                .'create the method "setAxes()"?',
            get_class($car),
            implode(', ', (array) $singulars = FormUtil::singularify('Axes'))
        );
        $data[] = array($car, $propertyPath, $expectedMessage);

        return $data;
    }
}
