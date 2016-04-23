<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Formatter;

/**
 * Returns a string representation of a string or array callable.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallableToStringFormatter implements StringFormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports($value)
    {
        return is_callable($value) && !$value instanceof \Closure;
    }

    /**
     * {@inheritdoc}
     */
    public function formatToString($value)
    {
        if (is_string($value)) {
            return sprintf('(function) "%s"', $value);
        }

        $caller = is_object($value) ? get_class($value) : (is_object($value[0]) ? get_class($value[0]) : $value[0]);
        if (is_object($value) || (is_object($value[0]) && isset($value[1]) && '__invoke' === $value[1])) {
            return sprintf('(invokable) "%s"', $caller);
        }

        $method = $value[1];
        if (false !== $cut = strpos($method, $caller)) {
            $method = substr($method, $cut);
        }

        if ((new \ReflectionMethod($caller, $method))->isStatic()) {
            return sprintf('(static) "%s::%s"', $caller, $method);
        }

        return sprintf('(callable) "%s::%s"', $caller, $method);
    }
}
