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
use Symfony\Component\DependencyInjection\Argument\EnvClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveEnvPlaceholdersPassTest extends TestCase
{
    public function testEnvClosureArgumentIsFormatted()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass')
            ->addArgument(new EnvClosureArgument($container->getParameterBag()->resolveValue('%env(FOO)%'), 'bar', true));

        (new ResolveEnvPlaceholdersPass(null))->process($container);

        $argument = $container->getDefinition('foo')->getArgument(0);
        $this->assertInstanceOf(EnvClosureArgument::class, $argument);
        $this->assertSame('%env(FOO)%', $argument->getValue());
        $this->assertSame('bar', $argument->getDefault());
        $this->assertTrue($argument->isStringable());
    }

    public function testEnvClosureArgumentIsNotResolvedToTheValueOfTheEnvVar()
    {
        $container = new ContainerBuilder();
        $argument = new EnvClosureArgument($container->getParameterBag()->resolveValue('%env(FOO)%'));
        $container->register('foo', 'stdClass')->addArgument($argument);

        (new ResolveEnvPlaceholdersPass())->process($container);

        $this->assertSame($argument, $container->getDefinition('foo')->getArgument(0));
    }
}
