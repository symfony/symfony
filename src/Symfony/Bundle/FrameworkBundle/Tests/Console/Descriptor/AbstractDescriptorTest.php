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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getDescribeRouteCollectionTestData */
    public function testDescribeRouteCollection(DescriptorInterface $descriptor, RouteCollection $routes, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($routes)));
    }

    public function getDescribeRouteCollectionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRouteCollections());
    }

    /** @dataProvider getDescribeRouteTestData */
    public function testDescribeRoute(DescriptorInterface $descriptor, Route $route, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($route)));
    }

    public function getDescribeRouteTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRoutes());
    }

    /** @dataProvider getDescribeContainerParametersTestData */
    public function testDescribeContainerParameters(DescriptorInterface $descriptor, ParameterBag $parameters, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($parameters)));
    }

    public function getDescribeContainerParametersTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerParameters());
    }

    /** @dataProvider getDescribeContainerBuilderTestData */
    public function testDescribeContainerBuilder(DescriptorInterface $descriptor, ContainerBuilder $builder, array $options, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($builder, $options)));
    }

    public function getDescribeContainerBuilderTestData()
    {
        return $this->getContainerBuilderDescriptionTestData(ObjectsProvider::getContainerBuilders());
    }

    /** @dataProvider getDescribeContainerDefinitionTestData */
    public function testDescribeContainerDefinition(DescriptorInterface $descriptor, Definition $definition, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($definition)));
    }

    public function getDescribeContainerDefinitionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerDefinitions());
    }

    /** @dataProvider getDescribeContainerAliasTestData */
    public function testDescribeContainerAlias(DescriptorInterface $descriptor, Alias $alias, $expectedDescription)
    {
        $this->assertEquals(trim($expectedDescription), trim($descriptor->describe($alias)));
    }

    public function getDescribeContainerAliasTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerAliases());
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

    private function getContainerBuilderDescriptionTestData(array $objects)
    {
        $variations = array(
            'services' => array('type' => 'services', 'show_private' => true),
            'public_services' => array('type' => 'services', 'show_private' => false),
            'tag1_services' => array('type' => 'services', 'show_private' => true, 'tag' => 'tag1'),
        );

        $data = array();
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s_%s.%s', __DIR__, $name, $suffix, $this->getFormat()));
                $data[] = array($this->getDescriptor(), $object, $options, $description);
            }
        }

        return $data;
    }
}
