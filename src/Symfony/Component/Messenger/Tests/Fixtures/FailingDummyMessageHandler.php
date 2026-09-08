<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FailingDummyMessageHandler
{
    public static int $calls = 0;

    public function __invoke(DummyMessage $message): void
    {
        throw new \RuntimeException(\sprintf('Handling "%s" failed %d time(s).', $message->getMessage(), ++self::$calls));
    }
}
