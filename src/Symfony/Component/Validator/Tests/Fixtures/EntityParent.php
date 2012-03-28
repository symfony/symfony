<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints\NotNull;

class EntityParent
{
    protected $firstName;
    private $internal;

    /**
     * @NotNull
     */
    protected $other;
}
