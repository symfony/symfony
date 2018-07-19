<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class EnvelopeTest extends TestCase
{
    public function testConstruct()
    {
        $envelope = new Envelope($dummy = new DummyMessage('dummy'), array(
            $receivedConfig = new ReceivedMessage(),
        ));

        $this->assertSame($dummy, $envelope->getMessage());
        $this->assertArrayHasKey(ReceivedMessage::class, $configs = $envelope->all());
        $this->assertSame($receivedConfig, $configs[ReceivedMessage::class]);
    }

    public function testWithReturnsNewInstance()
    {
        $envelope = new Envelope(new DummyMessage('dummy'));

        $this->assertNotSame($envelope, $envelope->with(new ReceivedMessage()));
    }

    public function testGet()
    {
        $envelope = (new Envelope(new DummyMessage('dummy')))->with($config = new ReceivedMessage());

        $this->assertSame($config, $envelope->get(ReceivedMessage::class));
        $this->assertNull($envelope->get(ValidationConfiguration::class));
    }

    public function testAll()
    {
        $envelope = (new Envelope(new DummyMessage('dummy')))
            ->with($receivedConfig = new ReceivedMessage())
            ->with($validationConfig = new ValidationConfiguration(array('foo')))
        ;

        $configs = $envelope->all();
        $this->assertArrayHasKey(ReceivedMessage::class, $configs);
        $this->assertSame($receivedConfig, $configs[ReceivedMessage::class]);
        $this->assertArrayHasKey(ValidationConfiguration::class, $configs);
        $this->assertSame($validationConfig, $configs[ValidationConfiguration::class]);
    }
}
