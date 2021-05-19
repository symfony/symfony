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
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
}
