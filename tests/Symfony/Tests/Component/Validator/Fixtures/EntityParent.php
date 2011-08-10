<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

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
