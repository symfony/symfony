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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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

        $this->assertCount(2, $this->container->getDefinitions());
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
