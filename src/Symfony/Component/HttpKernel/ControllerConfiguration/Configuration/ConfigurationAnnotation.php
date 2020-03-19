<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration\Configuration;

use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationInterface;

abstract class ConfigurationAnnotation implements ConfigurationInterface
{
    public function __construct(array $values)
    {
        foreach ($values as $k => $v) {
            if (!method_exists($this, $name = 'set'.$k)) {
                throw new \RuntimeException(sprintf('Unknown key "%s" for annotation "@%s".', $k, static::class));
            }

            $this->$name($v);
        }
    }
}
