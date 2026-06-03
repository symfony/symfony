<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DependencyInjection\WorkflowValidatorPass;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowValidatorPassTest extends TestCase
{
    private ContainerBuilder $container;
    private WorkflowValidatorPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new WorkflowValidatorPass();
    }

    public function testNothingToDo()
    {
        $this->compilerPass->process($this->container);

        $this->assertFalse(DefinitionValidator::$called);
    }

    public function testValidate()
    {
        $this
            ->container
            ->register('my.workflow', WorkflowInterface::class)
            ->addTag('workflow', [
                'definition_id' => 'my.workflow.definition',
                'name' => 'my.workflow',
                'definition_validators' => [DefinitionValidator::class],
            ])
        ;

        $this
            ->container
            ->register('my.workflow.definition', Definition::class)
            ->setArguments([
                '$places' => [],
                '$transitions' => [],
            ])
        ;

        $this->compilerPass->process($this->container);

        $this->assertTrue(DefinitionValidator::$called);
    }
}

class DefinitionValidator implements DefinitionValidatorInterface
{
    public static bool $called = false;

    public function validate(Definition $definition, string $name): void
    {
        self::$called = true;
    }
}
