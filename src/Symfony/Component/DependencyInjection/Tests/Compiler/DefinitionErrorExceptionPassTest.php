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
use Symfony\Component\DependencyInjection\Compiler\DefinitionErrorExceptionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

class DefinitionErrorExceptionPassTest extends TestCase
{
    public function testThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Things went wrong!');
        $container = new ContainerBuilder();
        $def = new Definition();
        $def->addError('Things went wrong!');
        $def->addError('Now something else!');
        $container->register('foo_service_id')
            ->setArguments([
                $def,
            ]);

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);
    }

    public function testNoExceptionThrown()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $container->register('foo_service_id')
            ->setArguments([
                $def,
            ]);

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);
        $this->assertSame($def, $container->getDefinition('foo_service_id')->getArgument(0));
    }

    public function testSkipNestedErrors()
    {
        $container = new ContainerBuilder();

        $container->register('nested_error', 'stdClass')
            ->addError('Things went wrong!');

        $container->register('bar', 'stdClass')
            ->addArgument(new Reference('nested_error'));

        $container->register('foo', 'stdClass')
            ->addArgument(new Reference('bar', ContainerBuilder::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE));

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Things went wrong!');
        $container->get('foo');
    }

    public function testSkipErrorFromTag()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $def->addError('Things went wrong!');
        $def->addTag('container.error');
        $container->register('foo_service_id')
            ->setArguments([$def]);

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);
        $this->assertSame($def, $container->getDefinition('foo_service_id')->getArgument(0));
    }
}
