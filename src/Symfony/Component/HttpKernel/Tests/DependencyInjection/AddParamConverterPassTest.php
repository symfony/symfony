<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\AddParamConverterPass;

class AddParamConverterPassTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AddParamConverterPass
     */
    private $pass;

    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var Definition
     */
    private $managerDefinition;

    protected function setUp(): void
    {
        $this->pass = new AddParamConverterPass();
        $this->container = new ContainerBuilder();
        $this->managerDefinition = new Definition();
        $this->container->setDefinition('param_converter.manager', $this->managerDefinition);
        $this->container->setParameter('param_converter.disabled_converters', []);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testProcessNoOpNoManager()
    {
        $this->container->removeDefinition('param_converter.manager');
        $this->pass->process($this->container);
    }

    public function testProcessNoOpNoTaggedServices()
    {
        $this->pass->process($this->container);
        $this->assertCount(0, $this->managerDefinition->getMethodCalls());
    }

    public function testProcessAddsTaggedServices()
    {
        $paramConverter1 = new Definition();
        $paramConverter1->setTags([
            'request.param_converter' => [
                [
                    'priority' => 'false',
                ],
            ],
        ]);

        $paramConverter2 = new Definition();
        $paramConverter2->setTags([
            'request.param_converter' => [
                [
                    'converter' => 'foo',
                ],
            ],
        ]);

        $paramConverter3 = new Definition();
        $paramConverter3->setTags([
            'request.param_converter' => [
                [
                    'priority' => 5,
                ],
            ],
        ]);

        $this->container->setDefinition('param_converter_one', $paramConverter1);
        $this->container->setDefinition('param_converter_two', $paramConverter2);
        $this->container->setDefinition('param_converter_three', $paramConverter3);

        $this->pass->process($this->container);

        $methodCalls = $this->managerDefinition->getMethodCalls();
        $this->assertCount(3, $methodCalls);
        $this->assertEquals(['add', [new Reference('param_converter_one'), 0, null]], $methodCalls[0]);
        $this->assertEquals(['add', [new Reference('param_converter_two'), 0, 'foo']], $methodCalls[1]);
        $this->assertEquals(['add', [new Reference('param_converter_three'), 5, null]], $methodCalls[2]);
    }

    public function testProcessExplicitAddsTaggedServices()
    {
        $paramConverter1 = new Definition();
        $paramConverter1->setTags([
            'request.param_converter' => [
                [
                    'priority' => 'false',
                    'converter' => 'bar',
                ],
            ],
        ]);

        $paramConverter2 = new Definition();
        $paramConverter2->setTags([
            'request.param_converter' => [
                [
                    'converter' => 'foo',
                ],
            ],
        ]);

        $paramConverter3 = new Definition();
        $paramConverter3->setTags([
            'request.param_converter' => [
                [
                    'priority' => 5,
                    'converter' => 'baz',
                ],
            ],
        ]);

        $this->container->setDefinition('param_converter_one', $paramConverter1);
        $this->container->setDefinition('param_converter_two', $paramConverter2);
        $this->container->setDefinition('param_converter_three', $paramConverter3);

        $this->container->setParameter('param_converter.disabled_converters', ['bar', 'baz']);

        $this->pass->process($this->container);

        $methodCalls = $this->managerDefinition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals(['add', [new Reference('param_converter_two'), 0, 'foo']], $methodCalls[0]);
    }
}
