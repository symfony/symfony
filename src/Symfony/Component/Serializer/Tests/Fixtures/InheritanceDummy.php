<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class InheritanceDummy extends InheritanceParentDummy
{
    public $name;

    public function __construct($withValues = false)
    {
        if ($withValues) {
            $this->name = 'val_name';
            $this->age = 'val_age';
        }
    }


}
