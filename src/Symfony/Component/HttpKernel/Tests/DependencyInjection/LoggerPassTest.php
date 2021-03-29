<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class LoggerPassTest extends TestCase
{
    public function testAlwaysSetAutowiringAlias()
    {
        $container = new ContainerBuilder();
        $container->register('logger', 'Foo');

        (new LoggerPass())->process($container);

        $this->assertFalse($container->getAlias(LoggerInterface::class)->isPublic());
    }

    public function testDoNotOverrideExistingLogger()
    {
        $container = new ContainerBuilder();
        $container->register('logger', 'Foo');

        (new LoggerPass())->process($container);

        $this->assertSame('Foo', $container->getDefinition('logger')->getClass());
    }

    /**
     * @dataProvider providesEnvironments
     */
    public function testRegisterLogger(string $environment, string $expectedClass, bool $classExists = true)
    {
        ClassExistsMock::register(LoggerPass::class);
        ClassExistsMock::withMockedClasses([TestLogger::class => $classExists]);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', $environment);

        (new LoggerPass())->process($container);

        $definition = $container->getDefinition('logger');
        $this->assertSame($expectedClass, $definition->getClass());
        $this->assertFalse($definition->isPublic());
    }

    public function providesEnvironments()
    {
        yield 'Dev environment' => ['dev', Logger::class];
        yield 'Test environment' => ['test', TestLogger::class];
        yield 'Test environment, no TestLogger' => ['test', Logger::class, false];
    }
}
