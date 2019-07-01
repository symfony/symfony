<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Symfony\Component\ErrorCatcher\BufferingLogger as NewBufferingLogger;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4 and will be removed in 5.0, use "%s" instead.', BufferingLogger::class, NewBufferingLogger::class), E_USER_DEPRECATED);

use Psr\Log\AbstractLogger;

/**
 * A buffering logger that stacks logs for later.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 4.4 and will be removed in 5.0, use Symfony\Component\ErrorCatcher\BufferingLogger instead.
 */
class BufferingLogger extends AbstractLogger
{
    private $logs = [];

    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [$level, $message, $context];
    }

    public function cleanLogs()
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }
}
