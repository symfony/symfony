<?php


namespace Symfony\Component\AutoMapper\Tests\Fixtures;


class CircularBaz
{
    /** @var CircularFoo */
    public $foo;
}
