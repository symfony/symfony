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
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveAutowireInlineAttributesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class ResolveAutowireInlineAttributesPassTest extends TestCase
{
    public function testAttribute()
    {
        $container = new ContainerBuilder();
        $container->register(Foo::class)->setAutowired(true);

        $container->register('autowire_inline1', AutowireInlineAttributes1::class)
            ->setAutowired(true);

        $container->register('autowire_inline2', AutowireInlineAttributes2::class)
            ->setAutowired(true);

        (new ResolveNamedArgumentsPass())->process($container);
        (new ResolveClassPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);
        (new ResolveAutowireInlineAttributesPass())->process($container);
        (new AutowirePass())->process($container);

        $autowireInlineAttributes1 = $container->get('autowire_inline1');
        self::assertInstanceOf(AutowireInlineAttributes1::class, $autowireInlineAttributes1);

        $autowireInlineAttributes2 = $container->get('autowire_inline2');
        self::assertInstanceOf(AutowireInlineAttributes2::class, $autowireInlineAttributes2);
    }
}
