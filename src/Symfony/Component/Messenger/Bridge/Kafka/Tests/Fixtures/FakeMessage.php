<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures;

class FakeMessage
{
    public function __construct(public string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
