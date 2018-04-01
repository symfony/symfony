<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\WebServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symphony\Bundle\WebServerBundle\DependencyInjection\WebServerExtension;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class WebServerExtensionTest extends TestCase
{
    public function testLoad()
    {
        $container = new ContainerBuilder();
        (new WebServerExtension())->load(array(), $container);

        $this->assertTrue($container->hasDefinition('web_server.command.server_run'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_start'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_stop'));
        $this->assertTrue($container->hasDefinition('web_server.command.server_status'));
        $this->assertSame(class_exists(ConsoleFormatter::class), $container->hasDefinition('web_server.command.server_log'));
    }
}
