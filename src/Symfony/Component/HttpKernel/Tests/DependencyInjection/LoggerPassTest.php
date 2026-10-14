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
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\ResettableServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServicesResetter;
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

    public function testRegisterLogger()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        (new LoggerPass())->process($container);

        $definition = $container->getDefinition('logger');
        $this->assertSame(Logger::class, $definition->getClass());
        $this->assertFalse($definition->isPublic());
    }

    public function testRegisteredLoggerIsResetBetweenRequests()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        (new LoggerPass())->process($container);

        $this->assertSame([['method' => 'clear', 'on_invalid' => 'ignore']], $container->getDefinition('logger')->getTag('kernel.reset'));
    }

    public function testResetIgnoresDecoratorWithoutClearMethod()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->register('decorating_logger', NullLogger::class)
            ->setPublic(true)
            ->setDecoratedService('logger');
        $container->addCompilerPass(new LoggerPass());
        $container->addCompilerPass(new ResettableServicePass());
        $container->compile();

        $container->get('decorating_logger');

        $this->expectNotToPerformAssertions();

        $container->get('services_resetter')->reset();
    }

    public function testAutowiringAliasIsPreserved()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setAlias(LoggerInterface::class, 'my_logger');

        (new LoggerPass())->process($container);

        $this->assertSame('my_logger', (string) $container->getAlias(LoggerInterface::class));
    }
}
