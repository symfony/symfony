<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ReadOnlyDummy
{
    /**
     * @Serializer\ReadOnly()
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
