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

use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * @author Eriksen Costa <eriksen.costa@infranology.com.br>
 *
 * @since v2.3.0
 */
class MethodArgumentNotImplementedException extends NotImplementedException
{
    /**
     * Constructor
     *
     * @param string $methodName The method name that raised the exception
     * @param string $argName    The argument name that is not implemented
     *
     * @since v2.3.0
     */
    public function __construct($methodName, $argName)
    {
        $message = sprintf('The %s() method\'s argument $%s behavior is not implemented.', $methodName, $argName);
        parent::__construct($message);
    }
}
