<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidTypeException;

class ScalarNode extends BaseNode implements PrototypeNodeInterface
{
    public function setName($name)
    {
        $this->name = $name;
    }

    protected function validateType($value)
    {
        if (!is_scalar($value)) {
            throw new \InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected scalar, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }

    protected function normalizeValue($value)
    {
        return $value;
    }
}