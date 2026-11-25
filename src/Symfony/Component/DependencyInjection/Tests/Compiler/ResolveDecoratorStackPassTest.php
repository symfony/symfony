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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveDecoratorStackPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

class ResolveDecoratorStackPassTest extends TestCase
{
    public function testProcessWithNumericLookingIds()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('01', (new ChildDefinition(''))->addTag('container.stack')->setArguments([new Reference('001')]));
        $container->setDefinition('001', (new ChildDefinition(''))->addTag('container.stack')->setArguments([new Definition(\stdClass::class)]));

        (new ResolveDecoratorStackPass())->process($container);

        $this->assertTrue($container->hasAlias('01'));
        $this->assertTrue($container->hasAlias('001'));
    }

    public function testProcessDetectsCircularStackWithIntegerLikeId()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('1', (new ChildDefinition(''))->addTag('container.stack')->setArguments([new Reference('01')]));
        $container->setDefinition('01', (new ChildDefinition(''))->addTag('container.stack')->setArguments([new Reference('1')]));

        $this->expectException(ServiceCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for service "1", path: "1 -> 01 -> 1".');

        (new ResolveDecoratorStackPass())->process($container);
    }
}
