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
    protected $allowEmptyValue = false;

    /**
     * {@inheritdoc}
     */
    protected function validateType($value)
    {
        if ($this->allowEmptyValue && $this->isValueEmpty($value)) {
            return;
        }

        if (!\is_bool($value)) {
            $ex = new InvalidTypeException(sprintf('Invalid type for path "%s". Expected boolean, but got %s.', $this->getPath(), \gettype($value)));
            if ($hint = $this->getInfo()) {
                $ex->addHint($hint);
            }
            $ex->setPath($this->getPath());

            throw $ex;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function isValueEmpty($value)
    {
        // assume environment variables are never empty (which in practice is likely to be true during runtime)
        // not doing so breaks many configs that are valid today
        if ($this->isHandlingPlaceholder()) {
            return false;
        }

        return null === $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function getValidPlaceholderTypes(): array
    {
        return ['bool'];
    }
}
