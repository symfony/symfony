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
    /**
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        if (!empty($data)) {
            throw new InvalidArgumentException(sprintf('No parameter is allowed for annotation "%s".', get_class($this)));
        }
    }

    /**
     * @return bool
     */
    public function getValue()
    {
        return true;
    }
}
