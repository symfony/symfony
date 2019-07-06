<?php

declare(strict_types=1);

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\InMemoryTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

class InMemoryTransportTest extends TestCase
{
    public function testItShouldSaveMessages()
    {
        $email = $this->createEmailMessage();

        $inMemoryTransport = new InMemoryTransport();
        $inMemoryTransport->send($email);

        /** @var SentMessage[] $inMemoryMessages */
        $inMemoryMessages = $inMemoryTransport->get();

        $this->assertCount(1, $inMemoryMessages);
        $this->assertSame(
            $email->getSender()->toString(),
            $inMemoryMessages[0]->getEnvelope()->getSender()->toString()
        );
    }

    public function testItShouldResetTransport()
    {
        $email = $this->createEmailMessage();

        $inMemoryTransport = new InMemoryTransport();
        $inMemoryTransport->send($email);

        $this->assertCount(1, $inMemoryTransport->get());

        $inMemoryTransport->reset();

        $this->assertCount(0, $inMemoryTransport->get());
    }

    private function createEmailMessage(): Message
    {
        return (new Email())
            ->sender('schaedlich.jan@gmail.com')
            ->to('jan.schaedlich@sensiolabs.de')
            ->subject('Important Notification')
            ->text('Lorem ipsum...');
    }
}
