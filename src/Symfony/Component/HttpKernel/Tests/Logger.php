<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    protected array $logs;

    public function __construct()
    {
        $this->clear();
    }

    public function getLogsForLevel(string $level): array
    {
        return $this->logs[$level];
    }

    public function clear(): void
    {
        $this->logs = [
            'emergency' => [],
            'alert' => [],
            'critical' => [],
            'error' => [],
            'warning' => [],
            'notice' => [],
            'info' => [],
            'debug' => [],
        ];
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[$level][] = $message;
    }
}
