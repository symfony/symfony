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

use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\NoSuchOptionException;
use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;

/**
 * Contains resolved option values.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
interface Options extends \ArrayAccess, \Countable
{
    /**
     * Returns the resolved value of an option.
     *
     * @param string $option             The option name
     * @param bool   $triggerDeprecation Whether to trigger the deprecation or not
     *
     * @return mixed The option value
     *
     * @throws AccessException           If accessing this method outside of
     *                                   {@link resolve()}
     * @throws NoSuchOptionException     If the option is not set
     * @throws InvalidOptionsException   If the option doesn't fulfill the
     *                                   specified validation rules
     * @throws OptionDefinitionException If there is a cyclic dependency between
     *                                   lazy options and/or normalizers
     */
    public function offsetGet($option, bool $triggerDeprecation = true);
}
