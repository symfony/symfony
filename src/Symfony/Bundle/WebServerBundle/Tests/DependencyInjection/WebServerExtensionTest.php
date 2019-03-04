<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bundle\WebServerBundle\DependencyInjection\WebServerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WebServerExtensionTest extends TestCase
{
    public function testLoad()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        (new WebServerExtension())->load([], $container);

        $this->assertSame(
            __DIR__.'/test',
            $container->getDefinition('web_server.command.server_run')->getArgument(0)
        );
        $this->assertSame(
            __DIR__.'/test',
            $container->getDefinition('web_server.command.server_start')->getArgument(0)
        );
        $this->assertTrue($container->hasDefinition('web_server.command.server_run'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_start'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_stop'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_status'));
        $this->assertSame(class_exists(ConsoleFormatter::class), $container->hasDefinition('web_server.command.server_log'));
    }
}
