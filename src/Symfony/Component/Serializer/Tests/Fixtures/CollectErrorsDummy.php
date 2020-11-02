<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

class CollectErrorsDummy
{
    /**
     * @var int
     */
    public $int;

    /**
     * @var array
     */
    public $array;

    /**
     * @var CollectErrorsArrayDummy[]
     */
    public $arrayOfObjects;

    /**
     * @var CollectErrorsArrayDummy[]
     */
    public $stringArrayOfObjects;

    /**
     * @var CollectErrorsObjectDummy
     */
    public $object;

    /**
     * @var CollectErrorsVariadicObjectDummy
     */
    public $variadicObject;
}
