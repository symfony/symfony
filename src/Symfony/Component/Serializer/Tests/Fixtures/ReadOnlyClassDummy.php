<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @Serializer\ReadOnly()
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ReadOnlyClassDummy
{
    /**
     * @Serializer\ReadOnly(false)
     */
    public $model;

    public $size;

    /**
     * @Serializer\ReadOnly()
     */
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
