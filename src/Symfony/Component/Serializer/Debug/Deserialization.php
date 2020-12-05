<?php

namespace Symfony\Component\Serializer\Debug;

/**
 * Class Deserialization
 */
final class Deserialization extends SerializerAction
{
    /**
     * @var string
     */
    public $type;

    public function __construct($data, string $type, string $format, array $context = [])
    {
        parent::__construct($data, $format, $context);
        $this->type = $type;
    }
}
