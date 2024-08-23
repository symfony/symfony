<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests\Fixtures\Handler;

use Psr\Log\LogLevel;
use Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper\Logging;

final class MessageHandler
{
    #[Logging]
    public function __invoke(Message $message): Message
    {
        return $message;
    }

    #[Logging(LogLevel::INFO)]
    public function handle1(Message $message): Message
    {
        return $message;
    }

    #[Logging]
    public static function handle2(Message $message): Message
    {
        return $message;
    }
}
