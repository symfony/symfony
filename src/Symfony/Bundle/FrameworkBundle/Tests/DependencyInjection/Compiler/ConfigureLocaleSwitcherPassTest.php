<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConfigureLocaleSwitcherPass;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ConfigureLocaleSwitcherPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('translation.locale_switcher')->setArgument(0, 'en');
        $container->register('locale_aware_service')
            ->addTag('kernel.locale_aware')
        ;

        $pass = new ConfigureLocaleSwitcherPass();
        $pass->process($container);

        $switcherDef = $container->getDefinition('translation.locale_switcher');

        $this->assertInstanceOf(IteratorArgument::class, $switcherDef->getArgument(1));
        $this->assertEquals([new Reference('locale_aware_service')], $switcherDef->getArgument(1)->getValues());
    }

    public function testProcessWithRequestContext()
    {
        $container = new ContainerBuilder();
        $container->register('translation.locale_switcher');
        $container->register('locale_aware_service')
            ->addTag('kernel.locale_aware')
        ;
        $container->register('translation.locale_aware_request_context');

        $pass = new ConfigureLocaleSwitcherPass();
        $pass->process($container);

        $switcherDef = $container->getDefinition('translation.locale_switcher');

        $this->assertInstanceOf(IteratorArgument::class, $switcherDef->getArgument(1));
        $this->assertEquals(
            [
                new Reference('locale_aware_service'),
                new Reference('translation.locale_aware_request_context'),
            ],
            $switcherDef->getArgument(1)->getValues()
        );
    }
}
