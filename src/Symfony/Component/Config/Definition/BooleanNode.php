<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * This node represents a Boolean value in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class BooleanNode extends ScalarNode
{
    protected function validateType(mixed $value)
    {
        if (!\is_bool($value)) {
            $ex = new InvalidTypeException(sprintf('Invalid type for path "%s". Expected "bool", but got "%s".', $this->getPath(), get_debug_type($value)));
            if ($hint = $this->getInfo()) {
                $ex->addHint($hint);
            }
            $ex->setPath($this->getPath());

            throw $ex;
        }
    }

    protected function isValueEmpty(mixed $value): bool
    {
        // a boolean value cannot be empty
        return false;
    }

    protected function getValidPlaceholderTypes(): array
    {
        return ['bool'];
    }
}
