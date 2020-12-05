<?php

namespace Symfony\Component\Serializer\Debug;

/**
 * Class SerializerAction
 */
abstract class SerializerAction
{
    /**
     * @var mixed
     */
    public $data;
    /**
     * @var string
     */
    public $format;
    /**
     * @var array
     */
    public $context;
    /**
     * @var mixed
     */
    public $result;

    public function __construct($data, string $format, array $context = [])
    {
        $this->data = $data;
        $this->format = $format;
        $this->context = $context;
    }
}
