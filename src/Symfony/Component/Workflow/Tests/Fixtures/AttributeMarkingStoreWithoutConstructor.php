<?php

namespace Symfony\Component\Workflow\Tests\Fixtures;

use Symfony\Component\Workflow\Attribute\AsMarkingStore;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\AbstractMarkingStore;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

#[AsMarkingStore(markingName: 'currentPlace', property: 'another')]
class AttributeMarkingStoreWithoutConstructor
{
}
