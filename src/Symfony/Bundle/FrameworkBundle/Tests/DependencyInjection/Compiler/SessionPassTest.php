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
        $container = new ContainerBuilder();
        $container
            ->register('session.factory'); // marker service
        $container
            ->register('.session.do-not-use');

        (new SessionPass())->process($container);

        self::assertTrue($container->hasAlias('session'));
        self::assertSame($container->findDefinition('session'), $container->getDefinition('.session.do-not-use'));
        self::assertTrue($container->getAlias('session')->isDeprecated());
    }

    public function testProcessUserDefinedSession()
    {
        $arguments = [
            new Reference('session.flash_bag'),
            new Reference('session.attribute_bag'),
        ];
        $container = new ContainerBuilder();
        $container
            ->register('session.factory'); // marker service
        $container
            ->register('session')
            ->setArguments($arguments);
        $container
            ->register('session.flash_bag')
            ->setFactory([new Reference('.session.do-not-use'), 'getFlashBag']);
        $container
            ->register('session.attribute_bag')
            ->setFactory([new Reference('.session.do-not-use'), 'getAttributeBag']);

        (new SessionPass())->process($container);

        self::assertSame($arguments, $container->getDefinition('session')->getArguments());
        self::assertNull($container->getDefinition('session.flash_bag')->getFactory());
        self::assertNull($container->getDefinition('session.attribute_bag')->getFactory());
        self::assertTrue($container->hasAlias('.session.do-not-use'));
        self::assertSame($container->getDefinition('session'), $container->findDefinition('.session.do-not-use'));
        self::assertTrue($container->getDefinition('session')->isDeprecated());
    }

    public function testProcessUserDefinedAlias()
    {
        $arguments = [
            new Reference('session.flash_bag'),
            new Reference('session.attribute_bag'),
        ];
        $container = new ContainerBuilder();
        $container
            ->register('session.factory'); // marker service
        $container
            ->register('trueSession')
            ->setArguments($arguments);
        $container
            ->setAlias('session', 'trueSession');
        $container
            ->register('session.flash_bag')
            ->setFactory([new Reference('.session.do-not-use'), 'getFlashBag']);
        $container
            ->register('session.attribute_bag')
            ->setFactory([new Reference('.session.do-not-use'), 'getAttributeBag']);

        (new SessionPass())->process($container);

        self::assertSame($arguments, $container->findDefinition('session')->getArguments());
        self::assertNull($container->getDefinition('session.flash_bag')->getFactory());
        self::assertNull($container->getDefinition('session.attribute_bag')->getFactory());
        self::assertTrue($container->hasAlias('.session.do-not-use'));
        self::assertSame($container->findDefinition('session'), $container->findDefinition('.session.do-not-use'));
        self::assertTrue($container->getAlias('session')->isDeprecated());
    }
}
