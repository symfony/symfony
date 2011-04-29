<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

/**
 * @import("Symfony\Component\Validator\Constraints\*", alias="assert")
 */
class EntityParent
{
    protected $firstName;
    private $internal;

    /**
     * @assert:NotNull
     */
    protected $other;
}