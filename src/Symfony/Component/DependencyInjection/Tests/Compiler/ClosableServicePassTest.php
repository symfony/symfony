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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ClosableServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClosableService;

class ClosableServicePassTest extends TestCase
{
    protected function setUp(): void
    {
        ClosableService::$closed = 0;
    }

    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register('default_method', ClosableService::class)
            ->setPublic(true)
            ->addTag('kernel.close');
        $container->register('custom_methods', ClosableService::class)
            ->setPublic(true)
            ->addTag('kernel.close', ['method' => 'close'])
            ->addTag('kernel.close', ['method' => 'missing', 'on_invalid' => 'ignore']);
        $this->registerCloser($container);

        $container->compile();

        $this->assertEquals(
            [
                new IteratorArgument([
                    'default_method' => new Reference('default_method', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'custom_methods' => new Reference('custom_methods', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                ]),
                [
                    'default_method' => ['close'],
                    'custom_methods' => ['close', '?missing'],
                ],
            ],
            $container->getDefinition('services_closer')->getArguments()
        );
    }

    public function testOnlyInstantiatedServicesAreClosed()
    {
        $container = new ContainerBuilder();
        $container->register('used', ClosableService::class)
            ->setPublic(true)
            ->addTag('kernel.close');
        $container->register('unused', ClosableService::class)
            ->setPublic(true)
            ->addTag('kernel.close');
        $this->registerCloser($container);

        $container->compile();
        $container->get('used');
        $container->get('services_closer')->reset();

        $this->assertSame(1, ClosableService::$closed);
    }

    public function testNonSharedServiceIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('non_shared', ClosableService::class)
            ->setShared(false)
            ->addTag('kernel.close');
        $this->registerCloser($container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "non_shared" cannot be tagged "kernel.close" because it is not shared');

        $container->compile();
    }

    public function testCloserIsRemovedWithoutTaggedServices()
    {
        $container = new ContainerBuilder();
        $this->registerCloser($container);

        $container->compile();

        $this->assertFalse($container->has('services_closer'));
    }

    private function registerCloser(ContainerBuilder $container): void
    {
        $container->register('services_closer', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ClosableServicePass());
    }
}
