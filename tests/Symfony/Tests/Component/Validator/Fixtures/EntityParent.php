<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

class EntityParent
{
    protected $firstName;
    private $internal;

    /**
     * @assert:NotNull
     */
    protected $other;
}