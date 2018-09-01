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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

class DummyReceiver implements ReceiverInterface
{
    public function receive(callable $handler): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $handler(Envelope::wrap(new DummyMessage("Dummy $i")));
        }
    }

    public function stop(): void
    {
    }
}
