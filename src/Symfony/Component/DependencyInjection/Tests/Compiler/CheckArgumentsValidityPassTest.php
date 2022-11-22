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
use Symfony\Component\DependencyInjection\Compiler\CheckArgumentsValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CheckArgumentsValidityPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments([null, 1, 'a']);
        $definition->setMethodCalls([
            ['bar', ['a', 'b']],
            ['baz', ['c', 'd']],
        ]);

        $pass = new CheckArgumentsValidityPass();
        $pass->process($container);

        $this->assertEquals([null, 1, 'a'], $container->getDefinition('foo')->getArguments());
        $this->assertEquals([
            ['bar', ['a', 'b']],
            ['baz', ['c', 'd']],
        ], $container->getDefinition('foo')->getMethodCalls());
    }

    /**
     * @dataProvider definitionProvider
     */
    public function testException(array $arguments, array $methodCalls)
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments($arguments);
        $definition->setMethodCalls($methodCalls);

        $pass = new CheckArgumentsValidityPass();
        $this->expectException(RuntimeException::class);
        $pass->process($container);
    }

    public static function definitionProvider()
    {
        return [
            [['a' => 'a', null], []],
            [[1 => 1], []],
            [[], [['baz', ['a' => 'a', null]]]],
            [[], [['baz', [1 => 1]]]],
        ];
    }

    public function testNoException()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments(['a' => 'a', null]);

        $pass = new CheckArgumentsValidityPass(false);
        $pass->process($container);
        $this->assertCount(1, $definition->getErrors());
    }
}
