<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
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
        self::assertSame('workflow title', $metadataBag['title']);
    }

    public function testGetUnexistingPlaceMetadata()
    {
        $metadataBag = $this->store->getPlaceMetadata('place_b');
        self::assertSame([], $metadataBag);
    }

    public function testGetExistingPlaceMetadata()
    {
        $metadataBag = $this->store->getPlaceMetadata('place_a');
        self::assertSame('place_a title', $metadataBag['title']);
    }

    public function testGetUnexistingTransitionMetadata()
    {
        $metadataBag = $this->store->getTransitionMetadata(new Transition('transition_2', [], []));
        self::assertSame([], $metadataBag);
    }

    public function testGetExistingTransitionMetadata()
    {
        $metadataBag = $this->store->getTransitionMetadata($this->transition);
        self::assertSame('transition_1 title', $metadataBag['title']);
    }

    public function testGetMetadata()
    {
        self::assertSame('workflow title', $this->store->getMetadata('title'));
        self::assertNull($this->store->getMetadata('description'));
        self::assertSame('place_a title', $this->store->getMetadata('title', 'place_a'));
        self::assertNull($this->store->getMetadata('description', 'place_a'));
        self::assertNull($this->store->getMetadata('description', 'place_b'));
        self::assertSame('transition_1 title', $this->store->getMetadata('title', $this->transition));
        self::assertNull($this->store->getMetadata('description', $this->transition));
        self::assertNull($this->store->getMetadata('description', new Transition('transition_2', [], [])));
    }

    public function testGetMetadataWithUnknownType()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Could not find a MetadataBag for the subject of type "bool".');
        $this->store->getMetadata('title', true);
    }
}
