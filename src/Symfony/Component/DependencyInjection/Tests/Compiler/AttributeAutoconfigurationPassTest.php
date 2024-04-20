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
}
