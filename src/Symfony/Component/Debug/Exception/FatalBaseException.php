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
 * Base Fatal Error Exception.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FatalBaseException extends FatalErrorException
{
    public function __construct(\BaseException $e)
    {
        if ($e instanceof \ParseException) {
            $message = 'Parse error: '.$e->getMessage();
            $severity = E_PARSE;
        } elseif ($e instanceof \TypeException) {
            $message = 'Type error: '.$e->getMessage();
            $severity = E_RECOVERABLE_ERROR;
        } else {
            $message = 'Fatal error: '.$e->getMessage();
            $severity = E_ERROR;
        }

        \ErrorException::__construct(
            $message,
            $e->getCode(),
            $severity,
            $e->getFile(),
            $e->getLine()
        );

        $this->setTrace($e->getTrace());
    }
}
