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
use Symphony\Component\DependencyInjection\Compiler\CheckReferenceValidityPass;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class CheckReferenceValidityPassTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testProcessDetectsReferenceToAbstractDefinition()
    {
        $container = new ContainerBuilder();

        $container->register('a')->setAbstract(true);
        $container->register('b')->addArgument(new Reference('a'));

        $this->process($container);
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addArgument(new Reference('b'));
        $container->register('b');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new CheckReferenceValidityPass();
        $pass->process($container);
    }
}
