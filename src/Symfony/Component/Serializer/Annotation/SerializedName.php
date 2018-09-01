<?php

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class SerializedName
{
    /**
     * @var string
     */
    private $name;

    public function __construct(array $data)
    {
        if (!isset($data['value']) || !$data['value']) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', get_class($this)));
        }

        if (!is_string($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string.', get_class($this)));
        }

        $this->name = $data['value'];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
