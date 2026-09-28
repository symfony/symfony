<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\DataCollector\WorkflowDataCollector;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class WorkflowPanelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(WorkflowDataCollector::class)) {
            $this->markTestSkipped('The Workflow component is not installed.');
        }
    }

    public function testPanelEscapesTheDiagramSource()
    {
        $definition = new Definition(['draft', 'review'], [new Transition('submit', 'draft', 'review')], null, new InMemoryMetadataStore([], ['review' => ['label' => 'Amount<limit']]));

        $panel = $this->renderPanel(new Workflow($definition, null, null, 'blog'), new EventDispatcher());

        $this->assertStringContainsString('place1((&quot;Amount&lt;limit&quot;))', $panel);
    }

    public function testPanelEscapesTagsInTheListenerMap()
    {
        $guardListener = new GuardListener(
            ['workflow.blog.guard.publish' => ['not (subject.body matches "/<!--|<script>/")']],
            new ExpressionLanguage(),
            new TokenStorage(),
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(AuthenticationTrustResolverInterface::class)
        );
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.blog.guard.publish', [$guardListener, 'onTransition']);
        $definition = new Definition(['draft', 'published'], [new Transition('publish', 'draft', 'published')]);

        $panel = $this->renderPanel(new Workflow($definition, null, $dispatcher, 'blog'), $dispatcher);

        $this->assertStringContainsString('\u003C!--|\u003Cscript\u003E', $panel);
    }

    private function renderPanel(Workflow $workflow, EventDispatcher $dispatcher): string
    {
        $collector = new WorkflowDataCollector([$workflow], $dispatcher, new FileLinkFormatter());
        $collector->lateCollect();
        $collector = unserialize(serialize($collector));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new WebProfilerExtension());

        return $twig
            ->load('@WebProfiler/Collector/workflow.html.twig')
            ->renderBlock('panel', ['collector' => $collector])
        ;
    }
}
