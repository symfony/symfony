<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

/**
 * Error Exception with Variable Context.
 *
 * @author Christian Sciberras <uuf6429@gmail.com>
 *
 * @deprecated since version 3.3. Instead, \ErrorException will be used directly in 4.0.
 */
class ContextErrorException extends \ErrorException
{
    private $context = [];

    public function __construct($message, $code, $severity, $filename, $lineno, $context = [])
    {
        parent::__construct($message, $code, $severity, $filename, $lineno);
        $this->context = $context;
    }

    /**
     * @return array Array of variables that existed when the exception occurred
     */
    public function getContext()
    {
        @trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0.', __CLASS__), \E_USER_DEPRECATED);

        return $this->context;
    }
}
