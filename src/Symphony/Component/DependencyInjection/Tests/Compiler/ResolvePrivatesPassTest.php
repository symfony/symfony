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
use Symphony\Component\DependencyInjection\Compiler\ResolvePrivatesPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class ResolvePrivatesPassTest extends TestCase
{
    public function testPrivateHasHigherPrecedenceThanPublic()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'stdClass')
            ->setPublic(true)
            ->setPrivate(true)
        ;

        $container->setAlias('bar', 'foo')
            ->setPublic(false)
            ->setPrivate(false)
        ;

        (new ResolvePrivatesPass())->process($container);

        $this->assertFalse($container->getDefinition('foo')->isPublic());
        $this->assertFalse($container->getAlias('bar')->isPublic());
    }
}
