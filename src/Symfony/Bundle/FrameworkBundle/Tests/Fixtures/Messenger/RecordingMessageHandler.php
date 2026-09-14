<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RecordingMessageHandler
{
    public static array $handled = [];
    public static int $attempts = 0;
    public static int $failures = 0;

    public function __invoke(FooMessage|BarMessage $message): void
    {
        ++self::$attempts;

        if (0 < self::$failures--) {
            throw new \RuntimeException('Handling failed.');
        }

        self::$handled[] = $message;
    }
}
