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
 * A wrapper for an anonymous function.
 * We do not return anonymous functions directly for security reason, to avoid
 * calling arbitrary functions by returning arrays containing class/method or
 * string function names. From the userland, one can still get access to the
 * anonymous function using the various public methods.
 *
 * @author Christian Sciberras <christian@sciberras.me>
 */
class SafeCallable
{
    protected $callback;

    /**
     * Constructor.
     *
     * @param Callable $callback The target callback.
     */
    public function __construct(Callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return Callable
     */
    public function getCallback()
    {
        return $this->callback;
    }
    
    /**
     * Call the callback with the provided arguments and returns result.
     * @return mixed
     */
    public function call()
    {
        return $this->callArray(func_get_args());
    }
    
    /**
     * Call the callback with the provided arguments and returns result.
     * @param array $arguments
     * @return mixed
     */
    public function callArray(array $arguments)
    {
        $callback = $this->getCallback();
        return count($arguments)
            ? call_user_func_array($callback, $arguments)
            : $callback();
    }
    
    public function __invoke()
    {
        throw new Exception('Callback wrapper cannot be invoked, use $wrapper->getCallback() instead.');
    }
}
