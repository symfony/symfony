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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ErrorLoggerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Debug\ErrorHandlerConfigurator;

class ErrorLoggerCompilerPassTest extends TestCase
{
    public function testMonologLoggersAreUsed()
    {
        $container = $this->createContainer(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));

        (new ErrorLoggerCompilerPass())->process($container);

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertEquals(new Reference('monolog.logger.php'), $definition->getArgument(0));
        $this->assertEquals(new Reference('monolog.logger.deprecation'), $definition->getArgument(5));
    }

    public function testPhpLoggerIsNotUsedWhenLoggingIsDisabled()
    {
        $container = $this->createContainer(null);

        (new ErrorLoggerCompilerPass())->process($container);

        $definition = $container->getDefinition('debug.error_handler_configurator');
        $this->assertNull($definition->getArgument(0));
        $this->assertEquals(new Reference('monolog.logger.deprecation'), $definition->getArgument(5));
    }

    private function createContainer(?Reference $logger): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('monolog.logger.php');
        $container->register('monolog.logger.deprecation');
        $container->register('debug.error_handler_configurator', ErrorHandlerConfigurator::class)
            ->setArguments([$logger, null, null, true, true, null]);

        return $container;
    }
}
