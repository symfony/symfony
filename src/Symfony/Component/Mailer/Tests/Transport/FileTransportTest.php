<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mailer\Transport\FileTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @group time-sensitive
 */
class FileTransportTest extends TestCase
{
    public function testSend()
    {
        $path = sys_get_temp_dir().'/symfony/emails';
        (new Filesystem())->remove($path);

        $transport = new FileTransport($path);
        $message = new RawMessage('');
        $envelope = new SmtpEnvelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);
        $transport->send($message, $envelope);

        $file = glob($path.'/*')[0];
        /** @var SentMessage $sentMessage */
        $sentMessage = unserialize(file_get_contents($file));

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertEquals($envelope->getSender(), $sentMessage->getEnvelope()->getSender());
        $this->assertEquals($envelope->getRecipients(), $sentMessage->getEnvelope()->getRecipients());
        $this->assertEquals($message, $sentMessage->getMessage());
    }
}
