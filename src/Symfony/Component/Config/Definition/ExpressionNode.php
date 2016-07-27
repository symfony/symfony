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
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * This node represents a Symfony Expression Language Expression.
 *
 * @author Magnus Nordlander <magnus@fervo.se>
 */
class ExpressionNode extends VariableNode
{
    /**
     * {@inheritdoc}
     */
    protected function validateType($value)
    {
        if (null === $value) {
            return;
        }

        if (!$value instanceof Expression) {
            $e = new InvalidTypeException(sprintf('Invalid type for path "%s": expected Symfony\\Component\\ExpressionLanguage\\Expression , but got %s.', $this->getPath(), gettype($value)));

            if ($hint = $this->getInfo()) {
                $e->addHint($hint);
            }
            $e->setPath($this->getPath());

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function preNormalize($value)
    {
        $value = parent::preNormalize($value);

        return $this->transformToExpression($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        $value = parent::getDefaultValue();

        return $this->transformToExpression($value);
    }

    /**
     * {@inheritdoc}
     */
    protected function isValueEmpty($value)
    {
        return null === $value;
    }

    private function transformToExpression($value)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if ($value instanceof Expression) {
            return $value;
        }

        if (!class_exists(Expression::class)) {
            throw new \RuntimeException('The Symfony Expression Language component must be installed to use expression nodes in configurations.');
        }

        return new Expression($value);
    }
}
