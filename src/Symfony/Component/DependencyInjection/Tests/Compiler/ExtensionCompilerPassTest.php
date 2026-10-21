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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ExtensionCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class ExtensionCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private ExtensionCompilerPass $pass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->pass = new ExtensionCompilerPass();
    }

    public function testProcess()
    {
        $extension1 = new CompilerPassExtension('extension1');
        $extension2 = new DummyExtension('extension2');
        $extension3 = new DummyExtension('extension3');
        $extension4 = new CompilerPassExtension('extension4');

        $this->container->registerExtension($extension1);
        $this->container->registerExtension($extension2);
        $this->container->registerExtension($extension3);
        $this->container->registerExtension($extension4);

        $this->pass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('extension1'));
        $this->assertFalse($this->container->hasDefinition('extension2'));
        $this->assertFalse($this->container->hasDefinition('extension3'));
        $this->assertTrue($this->container->hasDefinition('extension4'));
    }

    public function testExtensionAlsoRegisteredAsCompilerPassIsProcessedOnceAtItsOwnPriority()
    {
        $extension = new class extends Extension implements CompilerPassInterface {
            public function getAlias(): string
            {
                return 'pass_extension';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $container->setParameter('test.processed', $container->hasParameter('test.processed') ? $container->getParameter('test.processed') + 1 : 1);
                $container->setParameter('test.processed_after_earlier_pass', $container->hasParameter('test.earlier_pass'));
            }
        };

        $this->container->registerExtension($extension);
        $this->container->addCompilerPass($extension, PassConfig::TYPE_BEFORE_OPTIMIZATION, -10000);
        $this->container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setParameter('test.earlier_pass', true);
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, -5000);

        $this->container->compile();

        $this->assertSame(1, $this->container->getParameter('test.processed'));
        $this->assertTrue($this->container->getParameter('test.processed_after_earlier_pass'));
    }
}

class DummyExtension extends Extension
{
    public function __construct(private readonly string $alias)
    {
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $container->register($this->alias);
    }
}

class CompilerPassExtension extends DummyExtension implements CompilerPassInterface
{
}
