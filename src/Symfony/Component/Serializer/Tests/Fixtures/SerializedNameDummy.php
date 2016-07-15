<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SerializedNameDummy
{
    /**
     * @Serializer\SerializedName("super_model")
     */
    public $model;

    public $carSize;

    public $color;

    public function __construct($withValues = false)
    {
        if ($withValues) {
            $this->model = 'val_model';
            $this->carSize = 'val_size';
            $this->color = 'val_color';
        }
    }
}
