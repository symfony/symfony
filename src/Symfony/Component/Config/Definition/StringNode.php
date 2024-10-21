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
 * This node represents a String value in the config tree.
 *
 * @author Raffaele Carelle <raffaele.carelle@gmail.com>
 */
class StringNode extends ScalarNode
{
    protected function validateType(mixed $value): void
    {
        if (!\is_string($value)) {
            $ex = new InvalidTypeException(\sprintf('Invalid type for path "%s". Expected "string", but got "%s".', $this->getPath(), get_debug_type($value)));
            if ($hint = $this->getInfo()) {
                $ex->addHint($hint);
            }
            $ex->setPath($this->getPath());

            throw $ex;
        }
    }

    protected function getValidPlaceholderTypes(): array
    {
        return ['string'];
    }
}
