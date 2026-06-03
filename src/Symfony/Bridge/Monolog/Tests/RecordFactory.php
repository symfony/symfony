<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

class RecordFactory
{
    public static function create(int|string|Level $level = 'warning', string|\Stringable $message = 'test', string $channel = 'test', array $context = [], \DateTimeImmutable $datetime = new \DateTimeImmutable(), array $extra = []): LogRecord
    {
        return new LogRecord(
            message: (string) $message,
            context: $context,
            level: Logger::toMonologLevel($level),
            channel: $channel,
            datetime: $datetime,
            extra: $extra,
        );
    }
}
