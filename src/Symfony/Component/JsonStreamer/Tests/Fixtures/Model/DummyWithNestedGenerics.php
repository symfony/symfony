<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithNestedGenerics
{
    /**
     * @var DummyWithGenerics<ClassicDummy>
     */
    public DummyWithGenerics $dummies;

    /**
     * @var list<DummyWithGenerics<ClassicDummy>>
     */
    public array $dummiesList = [];

    /**
     * @var ?DummyWithGenerics<ClassicDummy>
     */
    public ?DummyWithGenerics $nullableDummies = null;

    /**
     * @var DummyWithGenerics<DummyWithGenerics<ClassicDummy>>
     */
    public DummyWithGenerics $nestedDummies;

    /**
     * @var ?DummyWithGenerics<DummyWithNameAttributes>
     */
    public ?DummyWithGenerics $namedDummies = null;
}
