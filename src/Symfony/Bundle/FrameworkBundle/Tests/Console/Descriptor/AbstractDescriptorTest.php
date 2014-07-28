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

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractDescriptorTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getDescribeRouteCollectionTestData */
    public function testDescribeRouteCollection(RouteCollection $routes, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $routes);
    }

    public function getDescribeRouteCollectionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRouteCollections());
    }

    /** @dataProvider getDescribeRouteTestData */
    public function testDescribeRoute(Route $route, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $route);
    }

    public function getDescribeRouteTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getRoutes());
    }

    /** @dataProvider getDescribeContainerParametersTestData */
    public function testDescribeContainerParameters(ParameterBag $parameters, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $parameters);
    }

    public function getDescribeContainerParametersTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerParameters());
    }

    /** @dataProvider getDescribeContainerBuilderTestData */
    public function testDescribeContainerBuilder(ContainerBuilder $builder, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $builder, $options);
    }

    public function getDescribeContainerBuilderTestData()
    {
        return $this->getContainerBuilderDescriptionTestData(ObjectsProvider::getContainerBuilders());
    }

    /** @dataProvider getDescribeContainerDefinitionTestData */
    public function testDescribeContainerDefinition(Definition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }

    public function getDescribeContainerDefinitionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerDefinitions());
    }

    /** @dataProvider getDescribeContainerAliasTestData */
    public function testDescribeContainerAlias(Alias $alias, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $alias);
    }

    public function getDescribeContainerAliasTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getContainerAliases());
    }

    /** @dataProvider getDescribeContainerParameterTestData */
    public function testDescribeContainerParameter($parameter, $expectedDescription, array $options)
    {
        $this->assertDescription($expectedDescription, $parameter, $options);
    }

    public function getDescribeContainerParameterTestData()
    {
        $data = $this->getDescriptionTestData(ObjectsProvider::getContainerParameter());

        array_push($data[0], array('parameter' => 'database_name'));

        return $data;
    }

    abstract protected function getDescriptor();
    abstract protected function getFormat();

    private function assertDescription($expectedDescription, $describedObject, array $options = array())
    {
        $options['raw_output'] = true;
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $describedObject, $options);

        if ('json' === $this->getFormat()) {
            $this->assertEquals(json_decode($expectedDescription), json_decode($output->fetch()));
        } else {
            $this->assertEquals(trim($expectedDescription), trim(str_replace(PHP_EOL, "\n", $output->fetch())));
        }
    }

    private function getDescriptionTestData(array $objects)
    {
        $data = array();
        foreach ($objects as $name => $object) {
            $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s.%s', __DIR__, $name, $this->getFormat()));
            $data[] = array($object, $description);
        }

        return $data;
    }

    private function getContainerBuilderDescriptionTestData(array $objects)
    {
        $variations = array(
            'services' => array('show_private' => true),
            'public'   => array('show_private' => false),
            'tag1'     => array('show_private' => true, 'tag' => 'tag1'),
            'tags'     => array('group_by' => 'tags', 'show_private' => true)
        );

        $data = array();
        foreach ($objects as $name => $object) {
            foreach ($variations as $suffix => $options) {
                $description = file_get_contents(sprintf('%s/../../Fixtures/Descriptor/%s_%s.%s', __DIR__, $name, $suffix, $this->getFormat()));
                $data[] = array($object, $description, $options);
            }
        }

        return $data;
    }
}
