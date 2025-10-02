<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Configuration\AttributeReader;
use Symfony\Component\Workflow\WorkflowType;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $attributeReader;

    protected function setUp(): void
    {
        $this->attributeReader = new AttributeReader();
    }

    public function testReadWorkflowConfiguration()
    {
        $reflection = new \ReflectionClass(TestTaskWorkflow::class);
        $attribute = $reflection->getAttributes(AsWorkflow::class)[0]->newInstance();

        $config = $this->attributeReader->extractConfiguration($attribute, $reflection);

        // Test basic structure
        $this->assertArrayHasKey('type', $config);
        $this->assertArrayHasKey('marking_store', $config);
        $this->assertArrayHasKey('supports', $config);
        $this->assertArrayHasKey('places', $config);
        $this->assertArrayHasKey('transitions', $config);
        $this->assertArrayHasKey('metadata', $config);
        $this->assertArrayHasKey('audit_trail', $config);

        // Test specific values
        $this->assertEquals('state_machine', $config['type']);
        $this->assertEquals(['type' => 'method'], $config['marking_store']);
        $this->assertContains(\stdClass::class, $config['supports']);
        $this->assertTrue($config['audit_trail']['enabled']);

        // Test transitions
        $this->assertCount(3, $config['transitions']);

        // Test a specific transition structure
        $startProcess = $config['transitions'][0];
        $this->assertEquals('start_process', $startProcess['name']);
        $this->assertIsArray($startProcess['from']);
        $this->assertIsArray($startProcess['to']);
        $this->assertArrayHasKey('metadata', $startProcess);

        // Verify the normalized format with place and weight
        $this->assertEquals('new', $startProcess['from'][0]['place']);
        $this->assertEquals(1, $startProcess['from'][0]['weighted']);
        $this->assertEquals('processing', $startProcess['to'][0]['place']);
        $this->assertEquals(1, $startProcess['to'][0]['weighted']);
    }

    public function testReadMetadataAndEnum()
    {
        $reflection = new \ReflectionClass(TestArticleWorkflow::class);
        $attribute = $reflection->getAttributes(AsWorkflow::class)[0]->newInstance();

        $config = $this->attributeReader->extractConfiguration($attribute, $reflection);

        $this->assertArrayHasKey('metadata', $config);
        $this->assertEquals('A workflow to manage article', $config['metadata']['description']);

        // Test metadata in transitions
        $requestReview = $config['transitions'][0];
        $this->assertArrayHasKey('metadata', $requestReview);
        $this->assertEquals('Request Review', $requestReview['metadata']['title']);
        $this->assertNotNull($requestReview['guard']);

        $publish = $config['transitions'][1];
        $this->assertEquals('Publish Article', $publish['metadata']['title']);
        $this->assertEquals('success', $publish['metadata']['color']);

        // Test places metadata
        $this->assertIsArray($config['places']);
        $this->assertCount(3, $config['places']);
        $this->assertEquals('Draft place', $config['places'][0]['metadata']['label']);
        $this->assertEquals('Review place', $config['places'][1]['metadata']['label']);
        $this->assertEquals('Published place', $config['places'][2]['metadata']['label']);
    }
}

#[AsWorkflow(
    name: 'test_task_workflow',
    type: WorkflowType::StateMachine,
    supports: [\stdClass::class],
    markingStore: [
        'type' => 'method',
    ],
    auditTrail: true,
)]
class TestTaskWorkflow
{
    #[Transition(
        froms: ['new'],
        tos: ['processing'],
    )]
    public const START_PROCESS = 'start_process';

    #[Transition(
        froms: 'processing', // We test different formats for froms/tos
        tos: ['completed'],
    )]
    public const COMPLETE = 'complete';

    #[Transition(
        froms: ['processing'],
        tos: 'failed', // We test different formats for froms/tos
    )]
    public const FAIL = 'fail';
}

#[AsWorkflow(
    name: 'test_article_workflow',
    type: WorkflowType::Workflow,
    supports: [\stdClass::class],
    markingStore: [
        'type' => 'method',
    ],
    auditTrail: true,
    places: TestArticlePlace::class,
    metadata: ['description' => 'A workflow to manage article'],
)]
class TestArticleWorkflow
{
    #[Transition(
        froms: ['draft'],
        tos: ['review'],
        metadata: [
            'title' => 'Request Review',
            'description' => 'Submit article for review',
        ],
        guard: 'is_fully_authenticated()',
    )]
    public const REQUEST_REVIEW = 'request_review';

    #[Transition(
        froms: ['review'],
        tos: ['published'],
        metadata: [
            'title' => 'Publish Article',
            'description' => 'Make article public',
            'color' => 'success',
        ],
        guard: 'is_granted("ROLE_EDITOR")',
    )]
    public const PUBLISH = 'publish';
}

enum TestArticlePlace: string
{
    #[Place(metadata: ['label' => 'Draft place'])]
    case Draft = 'draft';

    #[Place(metadata: ['label' => 'Review place'])]
    case Review = 'review';

    #[Place(metadata: ['label' => 'Published place'])]
    case Published = 'published';
}
