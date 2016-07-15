<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExclusionPolicyDefaultDummy
{
    /**
     * @Serializer\Exclude
     */
    public $model;

    /**
     * @Serializer\Expose
     */
    public $size;

    public $color;

    public function __construct($withValues = false)
    {
        if ($withValues) {
            $this->model = 'val_model';
            $this->size = 'val_size';
            $this->color = 'val_color';
        }
    }
}
