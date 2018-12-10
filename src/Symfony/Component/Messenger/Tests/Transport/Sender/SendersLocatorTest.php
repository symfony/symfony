<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sender;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

class SendersLocatorTest extends TestCase
{
    public function testItReturnsTheSenderBasedOnTheMessageClass()
    {
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $locator = new SendersLocator(array(
            DummyMessage::class => array($sender),
        ));

        $this->assertSame(array($sender), iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))));
        $this->assertSame(array(), iterator_to_array($locator->getSenders(new Envelope(new SecondMessage()))));
    }

    public function testItYieldsProvidedSenderAliasAsKey()
    {
        $sender = $this->getMockBuilder(SenderInterface::class)->getMock();
        $locator = new SendersLocator(array(
            DummyMessage::class => array('dummy' => $sender),
        ));

        $this->assertSame(array('dummy' => $sender), iterator_to_array($locator->getSenders(new Envelope(new DummyMessage('a')))));
    }
}
