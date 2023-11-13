<?php

namespace Symfony\Component\Workflow\Tests\Fixtures;

use Symfony\Component\Workflow\Attribute\AsMarkingStore;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\AbstractMarkingStore;

#[AsMarkingStore('currentPlace')]
class AttributeMarkingStore extends AbstractMarkingStore
{
    public function getMarking(object $subject): Marking
    {
        return new Marking([$subject->{$this->property} => 1]);
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $subject->{$this->property} = key($marking->getPlaces());
    }

    public function getProperty(): string
    {
        return $this->property;
    }
}
