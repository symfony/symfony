<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class DummyWithAttributes
{
    #[DummyAttribute(type: 'foo', name: 'nameA', version: 1)]
    public $a;

    #[DummyAttribute(type: 'bar', name: 'nameB', version: 2)]
    public $b;

    #[DummyAttribute(type: 'baz', name: 'nameC', version: 3)]
    public $c;

    #[DummyAttribute('foo', 'nameD', 4)]
    public $d;

    #[DummyAttribute(type: 'foo', name: 'nameE1', version: 5)]
    #[DummyAttribute(type: 'foo', name: 'nameE2', version: 10)]
    public $e;

    public $f;
}
