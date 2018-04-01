<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Exception;

/**
 * This exception wraps exceptions whose messages contain a reference to an env parameter.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvParameterException extends InvalidArgumentException
{
    public function __construct(array $envs, \Exception $previous = null, string $message = 'Incompatible use of dynamic environment variables "%s" found in parameters.')
    {
        parent::__construct(sprintf($message, implode('", "', $envs)), 0, $previous);
    }
}
