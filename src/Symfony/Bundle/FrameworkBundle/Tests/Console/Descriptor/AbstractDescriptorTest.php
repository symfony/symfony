<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getDescribeRouteCollectionTestData */
    public function testDescribeRouteCollection(DescriptorInterface $descriptor, RouteCollection $routes, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($routes)));
    }

    /** @dataProvider getDescribeRouteTestData */
    public function testDescribeRoute(DescriptorInterface $descriptor, Route $route, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($route)));
    }

    public function getDescribeRouteCollectionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRouteCollections());
    }

    public function getDescribeRouteTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRoutes());
    }

    abstract protected function getDescriptor();
    abstract protected function getFormat();

    private function getDescriptionTestData(array $objects)
    {
        $data = array();
        foreach ($objects as $name => $object) {
            $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s.%s', __DIR__, $name, $this->getFormat()));
            $data[] = array($this->getDescriptor(), $object, $description);
        }

        return $data;
    }
}
