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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveAutowireInlineAttributesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class ResolveAutowireInlineAttributesPassTest extends TestCase
{
    public function testAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class, Foo::class)
            ->setAutowired(true);

        $container->register('autowire_inline1', AutowireInlineAttributes1::class)
            ->setAutowired(true);

        $container->register('autowire_inline2', AutowireInlineAttributes2::class)
            ->setArgument(1, 234)
            ->setAutowired(true);

        $container->register('autowire_inline3', AutowireInlineAttributes3::class)
            ->setAutowired(true);

        (new ResolveAutowireInlineAttributesPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);
        (new ResolveNamedArgumentsPass())->process($container);
        (new AutowirePass())->process($container);

        $a = $container->get('autowire_inline1');
        self::assertInstanceOf(AutowireInlineAttributes1::class, $a);

        $a = $container->get('autowire_inline2');
        self::assertInstanceOf(AutowireInlineAttributes2::class, $a);

        $a = $container->get('autowire_inline3');
        self::assertInstanceOf(AutowireInlineAttributes2::class, $a->inlined);
        self::assertSame(345, $a->inlined->bar);
    }

    public function testChildDefinition()
    {
        $container = new ContainerBuilder();

        $container->setDefinition('autowire_inline1', (new ChildDefinition('parent'))->setClass(AutowireInlineAttributes1::class))
            ->setAutowired(true);

        (new ResolveAutowireInlineAttributesPass())->process($container);

        $this->assertSame(['$inlined'], array_keys($container->getDefinition('autowire_inline1')->getArguments()));
    }
}
