<?php

namespace Symfony\Component\Workflow\Tests\Fixtures;

use Symfony\Component\Workflow\Attribute\AsMarkingStore;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\AbstractMarkingStore;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

#[AsMarkingStore(markingName: 'currentPlace', property: 'another')]
class AttributeMarkingStoreWithCustomProperty implements MarkingStoreInterface
{
    public function __construct(private string $another)
    {
    }

    public function getMarking(object $subject): Marking
    {
        return new Marking();
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
    }

    public function getAnother(): string
    {
        return $this->another;
    }
}
