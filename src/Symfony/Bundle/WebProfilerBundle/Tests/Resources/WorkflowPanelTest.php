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

    public function testPanelShowsTheDescriptionsAsTooltips()
    {
        $submit = new Transition('submit', 'draft', 'review');
        $transitionsMetadata = new \SplObjectStorage();
        $transitionsMetadata[$submit] = ['description' => 'Ask for a review'];
        $metadataStore = new InMemoryMetadataStore([], ['review' => ['description' => "Waiting for a \"go\" & <b>#1</b>\non Monday"]], $transitionsMetadata);
        $definition = new Definition(['draft', 'review'], [$submit], null, $metadataStore);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.blog.leave.draft', 'var_dump');
        $dispatcher->addListener('workflow.blog.guard.submit', 'var_dump');

        $panel = $this->renderPanel(new Workflow($definition, null, $dispatcher, 'blog'), $dispatcher);
        $callback = 'showNodeDetails'.hash('xxh128', 'blog');

        $this->assertStringContainsString("click place0 {$callback}\n", $panel);
        $this->assertStringContainsString("click place1 {$callback} \"Waiting for a #quot;go#quot; #amp; #lt;b#gt;#35;1#lt;/b#gt;\non Monday\"\n", $panel);
        $this->assertStringContainsString("click transition0 {$callback} \"Ask for a review\"\n", $panel);
        $this->assertSame(1, substr_count($panel, 'click transition0 '));
    }

    public function testPanelShowsTheWorkflowDescription()
    {
        $metadataStore = new InMemoryMetadataStore(['description' => "Review \"posts\" & <b>#1</b>\nbefore publishing"]);
        $definition = new Definition(['draft', 'review'], [new Transition('submit', 'draft', 'review')], null, $metadataStore);

        $panel = $this->renderPanel(new Workflow($definition, null, null, 'blog'), new EventDispatcher());

        $this->assertStringContainsString("<p class=\"text-muted\">Review &quot;posts&quot; &amp; &lt;b&gt;#1&lt;/b&gt;<br />\nbefore publishing</p>", $panel);
    }

    public function testPanelRendersProfilesWithoutDescriptions()
    {
        $metadataStore = new InMemoryMetadataStore(['description' => 'The blog workflow'], ['review' => ['description' => 'Waiting for a review']]);
        $definition = new Definition(['draft', 'review'], [new Transition('submit', 'draft', 'review')], null, $metadataStore);
        $collector = new WorkflowDataCollector([new Workflow($definition, null, null, 'blog')], new EventDispatcher(), new FileLinkFormatter());
        $collector->lateCollect();
        $data = $collector->__serialize();
        unset($data['data']['workflows']['blog']['description'], $data['data']['workflows']['blog']['descriptions']);
        $collector->__unserialize($data);

        $panel = $this->renderCollector($collector);

        $this->assertStringContainsString('<h3>Definition</h3>', $panel);
        $this->assertStringNotContainsString('The blog workflow', $panel);
        $this->assertStringNotContainsString('Waiting for a review', $panel);
    }

    private function renderPanel(Workflow $workflow, EventDispatcher $dispatcher): string
    {
        $collector = new WorkflowDataCollector([$workflow], $dispatcher, new FileLinkFormatter());
        $collector->lateCollect();

        return $this->renderCollector(unserialize(serialize($collector)));
    }

    private function renderCollector(WorkflowDataCollector $collector): string
    {
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
