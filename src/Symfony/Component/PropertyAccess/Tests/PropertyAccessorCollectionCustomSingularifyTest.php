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

use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularifyClass;

class PropertyAccessorCollectionTestCustomSingularify_Car
{
    private $axes;

    public function __construct($axes = null)
    {
        $this->axes = $axes;
    }

    // In the test, use a name which is returned by our custom singularify class
    public function addAxes2($axis)
    {
        $this->axes[] = $axis;
    }

    public function removeAxes2($axis)
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

class PropertyAccessorCollectionCustomSingularifyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor(false, false, new TestSingularifyClass());
    }

    public function testSetValueCallsAdderForCollectionsWithCustomSingularify()
    {
        $axesBefore = array(1 => 'second', 3 => 'fourth', 4 => 'fifth');
        $axesMerged = array(1 => 'first', 2 => 'second', 3 => 'third');
        $axesAfter = array(1 => 'second', 5 => 'first', 6 => 'third');

        // Don't use a mock in order to test whether the collections are
        // modified while iterating them
        $car = new PropertyAccessorCollectionTestCustomSingularify_Car($axesBefore);

        $this->propertyAccessor->setValue($car, 'axes', $axesMerged);

        $this->assertEquals($axesAfter, $car->getAxes());
    }
}
