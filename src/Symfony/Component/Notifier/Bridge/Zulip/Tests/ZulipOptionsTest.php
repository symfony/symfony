<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipOptions;

final class ZulipOptionsTest extends TestCase
{
    public function testZulipOptions()
    {
        $options = new ZulipOptions('Test Topic', 'user@example.com');

        $this->assertSame([
            'topic' => 'Test Topic',
            'recipient' => 'user@example.com',
        ], $options->toArray());

        $this->assertSame('user@example.com', $options->getRecipientId());
    }

    public function testZulipOptionsWithNullValues()
    {
        $options = new ZulipOptions();

        $this->assertSame([
            'topic' => null,
            'recipient' => null,
        ], $options->toArray());

        $this->assertNull($options->getRecipientId());
    }

    public function testZulipOptionsWithTopicMethod()
    {
        $options = (new ZulipOptions())
            ->topic('New Topic');

        $this->assertSame([
            'topic' => 'New Topic',
            'recipient' => null,
        ], $options->toArray());

        $this->assertNull($options->getRecipientId());
    }

    public function testZulipOptionsChaining()
    {
        $options = new ZulipOptions();

        $result = $options->topic('Discussion');

        $this->assertSame($options, $result);
        $this->assertSame([
            'topic' => 'Discussion',
            'recipient' => null,
        ], $options->toArray());
    }

    public function testZulipOptionsWithStream()
    {
        $options = new ZulipOptions('Deploy Notifications', 'engineering');

        $this->assertSame([
            'topic' => 'Deploy Notifications',
            'recipient' => 'engineering',
        ], $options->toArray());

        $this->assertSame('engineering', $options->getRecipientId());
    }

    public function testZulipOptionsModification()
    {
        $options = new ZulipOptions('Initial Topic', 'initial@example.com');

        $options->topic('Updated Topic');

        $this->assertSame([
            'topic' => 'Updated Topic',
            'recipient' => 'initial@example.com',
        ], $options->toArray());

        $this->assertSame('initial@example.com', $options->getRecipientId());
    }
}
