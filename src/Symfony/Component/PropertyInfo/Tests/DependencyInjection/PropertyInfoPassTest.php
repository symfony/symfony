<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;

class PropertyInfoPassTest extends TestCase
{
    /**
     * @dataProvider provideTags
     */
    public function testServicesAreOrderedAccordingToPriority($index, $tag)
    {
        $container = new ContainerBuilder();

        $definition = $container->register('property_info')->setArguments([null, null, null, null]);
        $container->register('n2')->addTag($tag, ['priority' => 100]);
        $container->register('n1')->addTag($tag, ['priority' => 200]);
        $container->register('n3')->addTag($tag);

        $propertyInfoPass = new PropertyInfoPass();
        $propertyInfoPass->process($container);

        $expected = new IteratorArgument([
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ]);
        $this->assertEquals($expected, $definition->getArgument($index));
    }

    public function provideTags()
    {
        return [
            [0, 'property_info.list_extractor'],
            [1, 'property_info.type_extractor'],
            [2, 'property_info.description_extractor'],
            [3, 'property_info.access_extractor'],
        ];
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $container = new ContainerBuilder();
        $propertyInfoExtractorDefinition = $container->register('property_info')
            ->setArguments([[], [], [], []]);

        $propertyInfoPass = new PropertyInfoPass();
        $propertyInfoPass->process($container);

        $this->assertEquals(new IteratorArgument([]), $propertyInfoExtractorDefinition->getArgument(0));
        $this->assertEquals(new IteratorArgument([]), $propertyInfoExtractorDefinition->getArgument(1));
        $this->assertEquals(new IteratorArgument([]), $propertyInfoExtractorDefinition->getArgument(2));
        $this->assertEquals(new IteratorArgument([]), $propertyInfoExtractorDefinition->getArgument(3));
    }
}
