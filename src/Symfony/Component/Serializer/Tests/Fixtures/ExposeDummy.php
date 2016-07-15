<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @Serializer\ExclusionPolicy("all")
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExposeDummy
{
    /**
     * @Serializer\Expose
     */
    public $model;

    public $size;

    public function __construct($withValues = false)
    {
        if ($withValues) {
            $this->model = 'val_model';
            $this->size = 'val_size';
        }
    }
}
