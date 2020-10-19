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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SessionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SessionPassTest extends TestCase
{
    public function testProcess()
    {
        $arguments = [
            new Reference('session.flash_bag'),
            new Reference('session.attribute_bag'),
        ];
        $container = new ContainerBuilder();
        $container
            ->register('session')
            ->setArguments($arguments);
        $container
            ->register('session.flash_bag')
            ->setFactory([new Reference('session'), 'getFlashBag']);
        $container
            ->register('session.attribute_bag')
            ->setFactory([new Reference('session'), 'getAttributeBag']);

        (new SessionPass())->process($container);

        $this->assertSame($arguments, $container->getDefinition('session')->getArguments());
        $this->assertNull($container->getDefinition('session.flash_bag')->getFactory());
        $this->assertNull($container->getDefinition('session.attribute_bag')->getFactory());
    }
}
