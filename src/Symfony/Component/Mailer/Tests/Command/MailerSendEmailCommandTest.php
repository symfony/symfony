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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Command\MailerSendEmailCommand;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerSendEmailCommandTest extends TestCase
{
    public function testSendMail()
    {
        $expectedMail = $this->createExpectedMailFromString(
            'a@symfony.com',
            'b@symfony.com',
            'Test',
            'body'
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($expectedMail);

        $command = new MailerSendEmailCommand($mailer);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('mailer:send-email'));
        $tester->execute([
            'from' => 'a@symfony.com',
            'to' => 'b@symfony.com',
            '--subject' => 'Test',
            '--body' => 'body',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Email was successfully sent to "b@symfony.com"', $tester->getDisplay());
    }

    public function testSendMailNoSubject()
    {
        $expectedMail = $this->createExpectedMailFromString(
            'a@symfony.com',
            'b@symfony.com',
            'Testing Mailer Component',
            'This is a test email.'
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($expectedMail);

        $command = new MailerSendEmailCommand($mailer);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('mailer:send-email'));
        $tester->execute([
            'from' => 'a@symfony.com',
            'to' => 'b@symfony.com',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Email was successfully sent to "b@symfony.com"', $tester->getDisplay());
    }

    public function testSendMailBodyFromFile()
    {
        $temporaryPath = $this->createTemporaryPath();
        file_put_contents($temporaryPath, 'Body from file');

        $expectedMail = $this->createExpectedMailFromFile(
            'a@symfony.com',
            'b@symfony.com',
            'Testing Mailer Component',
            'Body from file'
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($expectedMail);

        $command = new MailerSendEmailCommand($mailer);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('mailer:send-email'));
        $tester->execute([
            'from' => 'a@symfony.com',
            'to' => 'b@symfony.com',
            '--body' => $temporaryPath,
            '--body-source' => 'file',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Email was successfully sent to "b@symfony.com"', $tester->getDisplay());
    }

    private function createExpectedMailFromString(string $from, string $to, string $subject, string $body): Email
    {
        return (new Email())
            ->from($from)
            ->to($to)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->text($body)
            ->html(
                <<<HTML
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>$subject</title>
  </head>
  <body>
    <p>$body</p>
  </body>
</html>
HTML
            );
    }

    private function createExpectedMailFromFile(string $from, string $to, string $subject, string $body): Email
    {
        return (new Email())
            ->from($from)
            ->to($to)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->text($body)
            ->html($body);
    }

    private function createTemporaryPath(): string
    {
        return stream_get_meta_data(tmpfile())['uri'];
    }
}
