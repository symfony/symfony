<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class MailerTestCommandTest extends TestCase
{
    public function testSendsEmail()
    {
        $from = 'from@example.com';
        $to = 'to@example.com';
        $subject = 'Foobar';
        $body = 'Lorem ipsum dolor sit amet.';

        $mailer = $this->createMock(TransportInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with(self::callback(static fn (Email $message) => [$from, $to, $subject, $body] === [
                $message->getFrom()[0]->getAddress(),
                $message->getTo()[0]->getAddress(),
                $message->getSubject(),
                $message->getTextBody(),
            ]))
        ;

        $tester = new CommandTester(new MailerTestCommand($mailer));
        $tester->execute([
            'to' => $to,
            '--from' => $from,
            '--subject' => $subject,
            '--body' => $body,
        ]);
    }

    public function testUsesCustomTransport()
    {
        $transport = 'foobar';

        $mailer = $this->createMock(TransportInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with(self::callback(static fn (Email $message) => $message->getHeaders()->getHeaderBody('X-Transport') === $transport))
        ;

        $tester = new CommandTester(new MailerTestCommand($mailer));
        $tester->execute([
            'to' => 'to@example.com',
            '--transport' => $transport,
        ]);
    }
}
