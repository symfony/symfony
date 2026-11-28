<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class SelfReferencingDummyWithCollections
{
    public ?self $self = null;

    public mixed $mixed = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $dict = null;

    /**
     * @var list<array<string, int>>|null
     */
    public ?array $listOfDicts = null;

    /**
     * @var list<list<ClassicDummy>>|null
     */
    public ?array $listsOfObjects = null;
}
