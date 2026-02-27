<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class AttributeAutoconfigurationPassTest extends TestCase
{
    public function testProcessAddsNoEmptyInstanceofConditionals()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsTaggedItem::class, static function () {});
        $container->register('foo', \stdClass::class)
            ->setAutoconfigured(true)
        ;

        (new AttributeAutoconfigurationPass())->process($container);

        $this->assertSame([], $container->getDefinition('foo')->getInstanceofConditionals());
    }

    public function testAttributeConfiguratorCallableMissingType()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsTaggedItem::class, static function (ChildDefinition $definition, AsTaggedItem $attribute, $reflector) {});
        $container->register('foo', \stdClass::class)
            ->setAutoconfigured(true)
        ;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Argument "$reflector" of attribute autoconfigurator should have a type, use one or more of "\ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionParameter|\Reflector" in ');
        (new AttributeAutoconfigurationPass())->process($container);
    }

    public function testProcessesAbstractServicesWithContainerExcludedTag()
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(AsTaggedItem::class, static function (ChildDefinition $definition, AsTaggedItem $attribute, \ReflectionClass $reflector) {
            $definition->addTag('processed.tag');
        });

        // Create an abstract service with container.excluded tag and attributes
        $abstractService = $container->register('abstract_service', TestServiceWithAttributes::class)
            ->setAutoconfigured(true)
            ->setAbstract(true)
            ->addTag('container.excluded', ['source' => 'test']);

        (new AttributeAutoconfigurationPass())->process($container);

        // Abstract service with container.excluded tag should be processed
        $expected = [
            TestServiceWithAttributes::class => (new ChildDefinition(''))->addTag('processed.tag'),
        ];
        $this->assertEquals($expected, $abstractService->getInstanceofConditionals());
    }
}

#[AsTaggedItem]
class TestServiceWithAttributes
{
}
