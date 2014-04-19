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
 * Fatal Error Exception.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Konstanton Myakshin <koc-dp@yandex.ru>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FatalErrorException extends HandledErrorException
{
    public function __construct($message, $code, $severity, $filename, $lineno, $traceOffset = null)
    {
        parent::__construct($message, $code, $severity, $filename, $lineno);

        if (null !== $traceOffset) {
            if (function_exists('xdebug_get_function_stack')) {
                $trace = xdebug_get_function_stack();
                if (0 < $traceOffset) {
                    $trace = array_slice($trace, 0, -$traceOffset);
                }
                $trace = array_reverse($trace);

                foreach ($trace as $i => $frame) {
                    if (!isset($frame['type'])) {
                        //  XDebug pre 2.1.1 doesn't currently set the call type key http://bugs.xdebug.org/view.php?id=695
                        if (isset($frame['class'])) {
                            $trace[$i]['type'] = '::';
                        }
                    } elseif ('dynamic' === $frame['type']) {
                        $trace[$i]['type'] = '->';
                    } elseif ('static' === $frame['type']) {
                        $trace[$i]['type'] = '::';
                    }

                    // XDebug also has a different name for the parameters array
                    if (isset($frame['params']) && !isset($frame['args'])) {
                        $trace[$i]['args'] = $frame['params'];
                        unset($trace[$i]['params']);
                    }
                }
            } else {
                $trace = array();
            }

            $this->setTrace($trace);
        }
    }

    protected function setTrace($trace)
    {
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($this, $trace);
    }
}
