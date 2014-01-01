<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Exception;

/**
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 */
class MethodArgumentValueNotImplementedException extends NotImplementedException
{
    /**
     * Constructor
     *
     * @param string $methodName        The method name that raised the exception
     * @param string $argName           The argument name
     * @param string $argValue          The argument value that is not implemented
     * @param string $additionalMessage An optional additional message to append to the exception message
     */
    public function __construct($methodName, $argName, $argValue, $additionalMessage = '')
    {
        $message = sprintf(
            'The %s() method\'s argument $%s value %s behavior is not implemented.%s',
            $methodName,
            $argName,
            var_export($argValue, true),
            $additionalMessage !== '' ? ' '.$additionalMessage.'. ' : ''
        );

        parent::__construct($message);
    }
}
