<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\DataCollector\WorkflowDataCollector;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Workflow;

class WorkflowDataCollectorTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function test()
    {
        $workflow1 = new Workflow($this->createComplexWorkflowDefinition(), name: 'workflow1');
        $workflow2 = new Workflow($this->createSimpleWorkflowDefinition(), name: 'workflow2');
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.workflow2.leave.a', fn () => true);
        $dispatcher->addListener('workflow.workflow2.leave.a', [self::class, 'noop']);
        $dispatcher->addListener('workflow.workflow2.leave.a', [$this, 'noop']);
        $dispatcher->addListener('workflow.workflow2.leave.a', $this->noop(...));
        $dispatcher->addListener('workflow.workflow2.leave.a', 'var_dump');
        $guardListener = new GuardListener(
            ['workflow.workflow2.guard.t1' => ['my_expression']],
            $this->createMock(ExpressionLanguage::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(AuthorizationCheckerInterface::class),
            $this->createMock(AuthenticationTrustResolverInterface::class),
            $this->createMock(RoleHierarchyInterface::class),
            $this->createMock(ValidatorInterface::class)
        );
        $dispatcher->addListener('workflow.workflow2.guard.t1', [$guardListener, 'onTransition']);

        $collector = new WorkflowDataCollector(
            [$workflow1, $workflow2],
            $dispatcher,
            new FileLinkFormatter(),
        );

        $collector->lateCollect();

        $data = $collector->getWorkflows();

        $this->assertArrayHasKey('workflow1', $data);
        $this->assertArrayHasKey('dump', $data['workflow1']);
        $this->assertStringStartsWith("graph LR\n", $data['workflow1']['dump']);
        $this->assertArrayHasKey('listeners', $data['workflow1']);

        $this->assertSame([], $data['workflow1']['listeners']);
        $this->assertArrayHasKey('workflow2', $data);
        $this->assertArrayHasKey('dump', $data['workflow2']);
        $this->assertStringStartsWith("graph LR\n", $data['workflow1']['dump']);
        $this->assertArrayHasKey('listeners', $data['workflow2']);
        $listeners = $data['workflow2']['listeners'];
        $this->assertArrayHasKey('place0', $listeners);
        $this->assertArrayHasKey('workflow.workflow2.leave.a', $listeners['place0']);
        $descriptions = $listeners['place0']['workflow.workflow2.leave.a'];
        $this->assertCount(5, $descriptions);
        $this->assertStringContainsString('Closure', $descriptions[0]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\DataCollector\WorkflowDataCollectorTest::noop()', $descriptions[1]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\DataCollector\WorkflowDataCollectorTest::noop()', $descriptions[2]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\DataCollector\WorkflowDataCollectorTest::noop()', $descriptions[3]['title']);
        $this->assertSame('var_dump()', $descriptions[4]['title']);
        $this->assertArrayHasKey('transition0', $listeners);
        $this->assertArrayHasKey('workflow.workflow2.guard.t1', $listeners['transition0']);
        $this->assertSame('Symfony\Component\Workflow\EventListener\GuardListener::onTransition()', $listeners['transition0']['workflow.workflow2.guard.t1'][0]['title']);
        $this->assertSame(['my_expression'], $listeners['transition0']['workflow.workflow2.guard.t1'][0]['guardExpressions']);
    }

    public static function noop()
    {
    }
}
