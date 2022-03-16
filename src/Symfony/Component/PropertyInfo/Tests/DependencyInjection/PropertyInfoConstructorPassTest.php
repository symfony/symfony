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
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoConstructorPass;

class PropertyInfoConstructorPassTest extends TestCase
{
    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();

        $tag = 'property_info.constructor_extractor';
        $definition = $container->register('property_info.constructor_extractor')->setArguments([null, null]);
        $container->register('n2')->addTag($tag, ['priority' => 100]);
        $container->register('n1')->addTag($tag, ['priority' => 200]);
        $container->register('n3')->addTag($tag);

        $pass = new PropertyInfoConstructorPass();
        $pass->process($container);

        $expected = new IteratorArgument([
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ]);
        $this->assertEquals($expected, $definition->getArgument(0));
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $container = new ContainerBuilder();
        $propertyInfoExtractorDefinition = $container->register('property_info.constructor_extractor')
            ->setArguments([[]]);

        $pass = new PropertyInfoConstructorPass();
        $pass->process($container);

        $this->assertEquals(new IteratorArgument([]), $propertyInfoExtractorDefinition->getArgument(0));
    }
}
