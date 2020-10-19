<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

use Psr\Log\AbstractLogger;

/**
 * A buffering logger that stacks logs for later.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class BufferingLogger extends AbstractLogger
{
    private $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [$level, $message, $context];
    }

    public function cleanLogs(): array
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }

    public function __destruct()
    {
        foreach ($this->logs as [$level, $message, $context]) {
            if (false !== strpos($message, '{')) {
                foreach ($context as $key => $val) {
                    if (null === $val || is_scalar($val) || (\is_object($val) && \is_callable([$val, '__toString']))) {
                        $message = str_replace("{{$key}}", $val, $message);
                    } elseif ($val instanceof \DateTimeInterface) {
                        $message = str_replace("{{$key}}", $val->format(\DateTime::RFC3339), $message);
                    } elseif (\is_object($val)) {
                        $message = str_replace("{{$key}}", '[object '.\get_class($val).']', $message);
                    } else {
                        $message = str_replace("{{$key}}", '['.\gettype($val).']', $message);
                    }
                }
            }

            error_log(sprintf('%s [%s] %s', date(\DateTime::RFC3339), $level, $message));
        }
    }
}
