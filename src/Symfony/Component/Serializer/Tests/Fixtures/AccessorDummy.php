<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

class AccessorDummy
{
    /**
     * @Serializer\Accessor(getter="getModel", setter="setModel")
     */
    public $model = 'defaultValue';

    /**
     * @return mixed
     */
    public function getModel()
    {
        return 'getModel';
    }

    /**
     * @param mixed $model
     *
     * @return AccessorDummy
     */
    public function setModel($model)
    {
        $this->model = $model.'_setter';

        return $this;
    }
}
