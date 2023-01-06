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

use Monolog\Logger;
use Monolog\LogRecord;

class RecordFactory
{
    public static function create(int|string $level = 'warning', string|\Stringable $message = 'test', string $channel = 'test', array $context = [], \DateTimeImmutable $datetime = new \DateTimeImmutable(), array $extra = []): LogRecord|array
    {
        $level = Logger::toMonologLevel($level);

        if (Logger::API >= 3) {
            return new LogRecord(
                message: (string) $message,
                context: $context,
                level: $level,
                channel: $channel,
                datetime: $datetime,
                extra: $extra,
            );
        }

        return [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => $channel,
            // Monolog 1 had no support for DateTimeImmutable
            'datetime' => Logger::API >= 2 ? $datetime : \DateTime::createFromImmutable($datetime),
            'extra' => $extra,
        ];
    }
}
