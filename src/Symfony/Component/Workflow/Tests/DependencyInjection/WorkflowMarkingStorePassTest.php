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
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\DependencyInjection\WorkflowMarkingStorePass;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeMarkingStore;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeMarkingStoreWithCustomProperty;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeMarkingStoreWithoutConstructor;

class WorkflowMarkingStorePassTest extends TestCase
{
    private ContainerBuilder $container;
    private WorkflowMarkingStorePass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new WorkflowMarkingStorePass();
    }

    public function testRegistersMarkingStore()
    {
        $this->container->register(AttributeMarkingStore::class, AttributeMarkingStore::class)->setPublic(true)->setAutoconfigured(true);

        (new AttributeAutoconfigurationPass())->process($this->container);
        $this->compilerPass->process($this->container);
        $this->container->compile();

        $this->assertSame('currentPlace', $this->container->get(AttributeMarkingStore::class)->getProperty());
        $this->assertSame(['currentPlace'], $this->container->getDefinition(AttributeMarkingStore::class)->getArguments());
    }

    public function testRegistersMarkingStoreWithCustomPropertyForMarking()
    {
        $this->container->register(AttributeMarkingStoreWithCustomProperty::class, AttributeMarkingStoreWithCustomProperty::class)->setPublic(true)->setAutoconfigured(true);

        (new AttributeAutoconfigurationPass())->process($this->container);
        $this->compilerPass->process($this->container);
        $this->container->compile();

        $this->assertSame('currentPlace', $this->container->get(AttributeMarkingStoreWithCustomProperty::class)->getAnother());
        $this->assertSame(['currentPlace'], $this->container->getDefinition(AttributeMarkingStoreWithCustomProperty::class)->getArguments());
    }

    public function testRegisterMakingStoreWithoutConstructor()
    {
        $this->container->register(AttributeMarkingStoreWithoutConstructor::class, AttributeMarkingStoreWithoutConstructor::class)->setPublic(true)->setAutoconfigured(true);

        (new AttributeAutoconfigurationPass())->process($this->container);
        $this->compilerPass->process($this->container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Workflow\Tests\Fixtures\AttributeMarkingStoreWithoutConstructor" class doesn\'t have a constructor with a string type-hinted argument named "another".');
        $this->container->compile();
    }
}
