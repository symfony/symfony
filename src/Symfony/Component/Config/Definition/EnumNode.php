<?php

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\ScalarNode;

/**
 * Node which only allows a finite set of values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EnumNode extends ScalarNode
{
    private $values;

    public function __construct($name, NodeInterface $parent = null, array $values = array())
    {
        $values = array_unique($values);
        if (count($values) <= 1) {
            $message = '$values must contain at least two distinct elements.';
            if (null !== $this->getInfo()) {
                $message .= sprintf("\nHint: %s.", $this->getInfo());
            }
            throw new \InvalidArgumentException($message);
        }

        parent::__construct($name, $parent);
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }

    protected function finalizeValue($value)
    {
        $value = parent::finalizeValue($value);

        if (!in_array($value, $this->values, true)) {
            $message = sprintf(
                'The value %s is not allowed for path "%s". Permissible values: %s',
                json_encode($value),
                $this->getPath(),
                implode(', ', array_map('json_encode', $this->values))
            );
            if (null !== $this->getInfo()) {
                $message .= sprintf("\nHint: %s.", $this->getInfo());
            }
            $ex = new InvalidConfigurationException($message);
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }
}
