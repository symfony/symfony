<?php


namespace Symfony\Component\AutoMapper\Tests\Fixtures;


class CircularFoo
{
    /** @var CircularBar */
    public $bar;
}
