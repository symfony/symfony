<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * An implementation of ExpressionContextInterface that does most
 * of the heavy lifting.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
abstract class AbstractExpressionContext implements ExpressionContextInterface
{
    private $expressionLanguage;
    private $values;

    public function __construct(ExpressionLanguage $expressionLanguage = null)
    {
        $this->expressionLanguage = $expressionLanguage ?: new ExpressionLanguage();

        static::registerFunctions($this->expressionLanguage);
    }

    /**
     * {@inheritdoc}
     */
    public static function parse($expression, ExpressionLanguage $expressionLanguage = null)
    {
        $expressionLanguage = $expressionLanguage ?: new ExpressionLanguage();
        static::registerFunctions($expressionLanguage);

        return $expressionLanguage->parse($expression, static::getNames());
    }

    /**
     * {@inheritdoc}
     */
    public static function compile($expression, ExpressionLanguage $expressionLanguage = null)
    {
        $expressionLanguage = $expressionLanguage ?: new ExpressionLanguage();
        static::registerFunctions($expressionLanguage);

        return $expressionLanguage->compile($expression, static::getNames());
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($expression)
    {
        return $this->expressionLanguage->evaluate($expression, $this->getValues());
    }

    /**
     * Build the values to be used within this context.
     *
     * @return array
     */
    abstract protected function buildValues();

    /**
     * Get and cache the values to be used within this context.
     *
     * @return array
     */
    private function getValues()
    {
        if ($this->values) {
            return $this->values;
        }

        return $this->values = $this->buildValues();
    }
}
