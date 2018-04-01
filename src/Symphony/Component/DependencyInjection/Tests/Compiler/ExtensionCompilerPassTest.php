<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Compiler\ExtensionCompilerPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Extension\Extension;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class ExtensionCompilerPassTest extends TestCase
{
    private $container;
    private $pass;

    protected function setUp()
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
}

class DummyExtension extends Extension
{
    private $alias;

    public function __construct($alias)
    {
        $this->alias = $alias;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function process(ContainerBuilder $container)
    {
        $container->register($this->alias);
    }
}

class CompilerPassExtension extends DummyExtension implements CompilerPassInterface
{
}
