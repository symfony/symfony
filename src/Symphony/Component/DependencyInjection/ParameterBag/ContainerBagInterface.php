<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\ParameterBag;

use Psr\Container\ContainerInterface;
use Symphony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ContainerBagInterface extends ContainerInterface
{
    /**
     * Gets the service container parameters.
     *
     * @return array An array of parameters
     */
    public function all();

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param mixed $value A value
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    public function resolveValue($value);

    /**
     * Escape parameter placeholders %.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function escapeValue($value);

    /**
     * Unescape parameter placeholders %.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function unescapeValue($value);
}
