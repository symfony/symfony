<?php

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Expose
{
    public function getValue(): bool
    {
        return true;
    }
}
