<?php

namespace Symfony\Component\Workflow\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class InMemoryMetadataStoreTest extends TestCase
{
    private $store;
    private $transition;

    protected function setUp(): void
    {
        $workflowMetadata = [
            'title' => 'workflow title',
        ];
        $placesMetadata = [
            'place_a' => [
                'title' => 'place_a title',
            ],
        ];
        $transitionsMetadata = new \SplObjectStorage();
        $this->transition = new Transition('transition_1', [], []);
        $transitionsMetadata[$this->transition] = [
            'title' => 'transition_1 title',
        ];

        $this->store = new InMemoryMetadataStore($workflowMetadata, $placesMetadata, $transitionsMetadata);
    }

    public function testGetWorkflowMetadata()
    {
        $metadataBag = $this->store->getWorkflowMetadata();
        $this->assertSame('workflow title', $metadataBag['title']);
    }

    public function testGetUnexistingPlaceMetadata()
    {
        $metadataBag = $this->store->getPlaceMetadata('place_b');
        $this->assertSame([], $metadataBag);
    }

    public function testGetExistingPlaceMetadata()
    {
        $metadataBag = $this->store->getPlaceMetadata('place_a');
        $this->assertSame('place_a title', $metadataBag['title']);
    }

    public function testGetUnexistingTransitionMetadata()
    {
        $metadataBag = $this->store->getTransitionMetadata(new Transition('transition_2', [], []));
        $this->assertSame([], $metadataBag);
    }

    public function testGetExistingTransitionMetadata()
    {
        $metadataBag = $this->store->getTransitionMetadata($this->transition);
        $this->assertSame('transition_1 title', $metadataBag['title']);
    }

    public function testGetMetadata()
    {
        $this->assertSame('workflow title', $this->store->getMetadata('title'));
        $this->assertNull($this->store->getMetadata('description'));
        $this->assertSame('place_a title', $this->store->getMetadata('title', 'place_a'));
        $this->assertNull($this->store->getMetadata('description', 'place_a'));
        $this->assertNull($this->store->getMetadata('description', 'place_b'));
        $this->assertSame('transition_1 title', $this->store->getMetadata('title', $this->transition));
        $this->assertNull($this->store->getMetadata('description', $this->transition));
        $this->assertNull($this->store->getMetadata('description', new Transition('transition_2', [], [])));
    }

    public function testGetMetadataWithUnknownType()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Could not find a MetadataBag for the subject of type "boolean".');
        $this->store->getMetadata('title', true);
    }
}
