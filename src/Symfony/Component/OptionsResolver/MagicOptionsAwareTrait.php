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

use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/**
 * Exposes magic option utilities for generic purposes.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait MagicOptionsAwareTrait
{
    use OptionsAwareTrait;

    /**
     * @throws \BadMethodCallException If the method is not a supported option method
     */
    public function __call($method, $arguments)
    {
        // @TODO allow for arbitrary options if $strictOptions is disabled?
        if (preg_match('~^(is|has|get|set)(.+)$~i', $method, $matches)) {
            $modifier = strtolower($matches[1]);
            array_unshift($arguments, lcfirst($matches[2]));
            try {
                $cast = ($modifier === 'is' || $modifier === 'has');
                $result = call_user_func_array(array($this, ($cast ? 'getOption' : $modifier.'Option')), $arguments);

                return $cast ? (bool) $result : $result;
            } catch (UndefinedOptionsException $e) {
                throw new \BadMethodCallException(sprintf('Method "%s" of %s does not exist', $method, get_class($this)), 0, $e);
            }
        }

        throw new \BadMethodCallException(sprintf('Method "%s" of %s does not exist', $method, get_class($this)));
    }

    public function __set($property, $value)
    {
        // @TODO allow for arbitrary options if $strictOptions is disabled?
        $this->setOption($property, $value);
    }

    public function __get($property)
    {
        // @TODO allow for arbitrary options if $strictOptions is disabled?
        return $this->getOption($property);
    }
}
