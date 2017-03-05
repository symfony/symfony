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
use Symfony\Component\DependencyInjection\Compiler\RemoveInvalidAutoregisteredPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;

class RemoveInvalidAutoregisteredPassTest extends TestCase
{
    public function testProcessWithMissingParameter()
    {
        $container = new ContainerBuilder();

        $container->register(NotFound::class)->addTag('kernel.autoregistered');
        $container->register(Autoregistered::class)->addTag('kernel.autoregistered');
        $container->register(AutoregisteredInterface::class)->addTag('kernel.autoregistered');

        $pass = new RemoveInvalidAutoregisteredPass();
        $pass->process($container);

        $expected = array(
            Autoregistered::class => (new Definition())->addTag('kernel.autoregistered'),
            'service_container' => (new Definition(ContainerInterface::class))->setSynthetic(true),
        );
        $this->assertEquals($expected, $container->getDefinitions());
    }
}

class Autoregistered
{
}

interface AutoregisteredInterface
{
}
