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
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\MultiResettableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ResettableServicePassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register('one', ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('two', ClearableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'clear']);
        $container->register('three', MultiResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'resetFirst'])
            ->addTag('kernel.reset', ['method' => 'resetSecond']);

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $definition = $container->getDefinition('services_resetter');

        $this->assertEquals(
            [
                new IteratorArgument([
                    'one' => new Reference('one', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'two' => new Reference('two', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'three' => new Reference('three', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                ]),
                [
                    'one' => ['reset'],
                    'two' => ['clear'],
                    'three' => ['resetFirst', 'resetSecond'],
                ],
            ],
            $definition->getArguments()
        );
    }

    public function testMissingMethod()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tag "kernel.reset" requires the "method" attribute to be set on service "Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService".');
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();
    }

    public function testIgnoreInvalidMethod()
    {
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', ['method' => 'missingMethod', 'on_invalid' => 'ignore']);
        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertSame([ResettableService::class => ['?missingMethod']], $container->getDefinition('services_resetter')->getArgument(1));

        $resettable = $container->get(ResettableService::class);
        $resetter = $container->get('services_resetter');
        $resetter->reset();
    }

    public function testCompilerPassWithoutResetters()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments([null, []]);
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has('services_resetter'));
    }
}
