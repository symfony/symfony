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

use Symfony\Component\Messenger\Inbox\InboxInterface;

class DummyInbox implements InboxInterface
{
    public array $records = [];

    public function record(string $consumer, string $messageId): bool
    {
        if (\in_array([$consumer, $messageId], $this->records, true)) {
            return false;
        }

        $this->records[] = [$consumer, $messageId];

        return true;
    }

    public function forget(string $consumer, string $messageId): void
    {
        $this->records = array_values(array_filter($this->records, static fn (array $record) => [$consumer, $messageId] !== $record));
    }
}
