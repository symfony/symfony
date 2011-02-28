<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

class BooleanNode extends ScalarNode
{
    /**
     * {@inheritDoc}
     */
    protected function validateType($value)
    {
        if (!is_bool($value)) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected boolean, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }
}