<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidTypeException;

class BooleanNode extends ScalarNode
{
    protected function validateType($value)
    {
        parent::validateType($value);

        if (!is_bool($value)) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected boolean, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }
}