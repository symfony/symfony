<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception wraps exceptions whose messages contain a reference to an env parameter.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SecretParameterException extends InvalidArgumentException
{
    public function __construct(array $secrets, \Exception $previous = null, $message = 'Incompatible use of dynamic environment variables "%s" found in parameters.')
    {
        parent::__construct(sprintf($message, implode('", "', $secrets)), 0, $previous);
    }
}
