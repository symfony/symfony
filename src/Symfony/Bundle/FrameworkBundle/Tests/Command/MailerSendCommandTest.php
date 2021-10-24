<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\MailerSendCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @author Fritz Michael Gschwantner <fmg@inspiredminds.at>
 */
class MailerSendCommandTest extends TestCase
{
    public function testSendsEmail()
    {
        $from = 'from@example.com';
        $to = 'to@example.com';
        $subject = 'Foobar';
        $body = 'Lorem ipsum dolor sit amet.';

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with(self::callback(static function (Email $message) use ($from, $to, $subject, $body): bool {
                return 
                    $message->getFrom()[0]->getAddress() === $from && 
                    $message->getTo()[0]->getAddress() === $to &&
                    $message->getSubject() === $subject &&
                    $message->getTextBody() === $body
                ;
            }))
        ;

        $command = new MailerSendCommand($mailer);

        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => $from,
            '--to' => $to,
            '--subject' => $subject,
            '--body' => $body,
        ]);
    }

    public function testUsesCustomTransport()
    {
        $transport = 'foobar';

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with(self::callback(static function (Email $message) use ($transport): bool {
                return $message->getHeaders()->getHeaderBody('X-Transport') === $transport;
            }))
        ;

        $command = new MailerSendCommand($mailer);

        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => 'from@example.com',
            '--to' => 'to@example.com',
            '--subject' => 'Foobar',
            '--body' => 'Lorem ipsum dolor sit amet.',
            '--transport' => $transport,
        ]);
    }
}
