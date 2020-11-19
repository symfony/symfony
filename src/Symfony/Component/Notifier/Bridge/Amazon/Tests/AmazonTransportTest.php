<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Amazon\Tests;

use AsyncAws\Sns\SnsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Amazon\AmazonTransport;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

class AmazonTransportTest extends TestCase
{

    public function testSupportsMessageInterface()
    {
        $transport = new AmazonTransport($this->createMock(SnsClient::class));

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }
}
