<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver;

/**
 * An option that is evaluated lazily using a closure.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyOption
{
    /**
     * The underlying closure.
     * @var \Closure
     */
    private $closure;

    /**
     * The previous default value of the option.
     * @var mixed
     */
    private $previousValue;

    /**
     * Creates a new lazy option.
     *
     * @param Closure $closure The closure used for initializing the
     *                               option value.
     * @param mixed $previousValue The previous value of the option. This
     *                               value is passed to the closure when it is
     *                               evaluated.
     *
     * @see evaluate()
     */
    public function __construct(\Closure $closure, $previousValue)
    {
        $this->closure = $closure;
        $this->previousValue = $previousValue;
    }

    /**
     * Evaluates the underyling closure and returns its result.
     *
     * The given Options instance is passed to the closure as first argument.
     * The previous default value set in the constructor is passed as second
     * argument.
     *
     * @param Options $options The container with all concrete options.
     *
     * @return mixed The result of the closure.
     */
    public function evaluate(Options $options)
    {
        if ($this->previousValue instanceof self) {
            $this->previousValue = $this->previousValue->evaluate($options);
        }

        return $this->closure->__invoke($options, $this->previousValue);
    }
}
