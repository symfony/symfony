<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Validator\ValidatorBuilder;
use Symfony\Component\Workflow\Debug\ListenerExtractor;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Workflow;

class ListenerExtractorTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function test()
    {
        $workflow1 = new Workflow($this->createComplexWorkflowDefinition(), name: 'workflow1');
        $workflow2 = new Workflow($this->createSimpleWorkflowDefinition(), name: 'workflow2');
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.workflow2.leave.a', static fn () => true);
        $dispatcher->addListener('workflow.workflow2.leave.a', [self::class, 'noop']);
        $dispatcher->addListener('workflow.workflow2.leave.a', [$this, 'noop']);
        $dispatcher->addListener('workflow.workflow2.leave.a', $this->noop(...));
        $dispatcher->addListener('workflow.workflow2.leave.a', 'var_dump');
        $guardListener = new GuardListener(
            ['workflow.workflow2.guard.t1' => ['my_expression']],
            new ExpressionLanguage(),
            new TokenStorage(),
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(AuthenticationTrustResolverInterface::class),
            new RoleHierarchy([]),
            (new ValidatorBuilder())->getValidator()
        );
        $dispatcher->addListener('workflow.workflow2.guard.t1', [$guardListener, 'onTransition']);

        $extractor = new ListenerExtractor($dispatcher, new FileLinkFormatter());

        $workflow1 = $extractor->extractListeners($workflow1->getName(), $workflow1->getDefinition());

        $this->assertSame([], $workflow1);

        $workflow2 = $extractor->extractListeners($workflow2->getName(), $workflow2->getDefinition());
        $this->assertArrayHasKey('place__a', $workflow2);
        $this->assertArrayHasKey('workflow.workflow2.leave.a', $workflow2['place__a']);
        $descriptions = $workflow2['place__a']['workflow.workflow2.leave.a'];
        $this->assertCount(5, $descriptions);
        $this->assertStringContainsString('Closure', $descriptions[0]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\Debug\ListenerExtractorTest::noop()', $descriptions[1]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\Debug\ListenerExtractorTest::noop()', $descriptions[2]['title']);
        $this->assertSame('Symfony\Component\Workflow\Tests\Debug\ListenerExtractorTest::noop()', $descriptions[3]['title']);
        $this->assertSame('var_dump()', $descriptions[4]['title']);
        $this->assertArrayHasKey('transition__0', $workflow2);
        $this->assertArrayHasKey('workflow.workflow2.guard.t1', $workflow2['transition__0']);
        $this->assertSame('Symfony\Component\Workflow\EventListener\GuardListener::onTransition()', $workflow2['transition__0']['workflow.workflow2.guard.t1'][0]['title']);
        $this->assertSame(['my_expression'], $workflow2['transition__0']['workflow.workflow2.guard.t1'][0]['guardExpressions']);
    }

    public static function noop()
    {
    }
}
