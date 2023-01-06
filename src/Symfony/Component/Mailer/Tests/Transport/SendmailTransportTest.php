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
use Symfony\Component\Mailer\DelayedEnvelope;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendmailTransportTest extends TestCase
{
    private const FAKE_SENDMAIL = __DIR__.'/Fixtures/fake-sendmail.php -t';

    /**
     * @var string
     */
    private $argsPath;

    protected function setUp(): void
    {
        $this->argsPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sendmail_args';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->argsPath)) {
            @unlink($this->argsPath);
        }
        unset($this->argsPath);
    }

    public function testToString()
    {
        $t = new SendmailTransport();
        $this->assertEquals('smtp://sendmail', (string) $t);
    }

    public function testToIsUsedWhenRecipientsAreNotSet()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support shebangs nor non-blocking standard streams');
        }

        $mail = new Email();
        $mail
            ->from('from@mail.com')
            ->to('to@mail.com')
            ->subject('Subject')
            ->text('Some text')
        ;

        $envelope = new DelayedEnvelope($mail);

        $sendmailTransport = new SendmailTransport(self::FAKE_SENDMAIL);
        $sendmailTransport->send($mail, $envelope);

        $this->assertStringEqualsFile($this->argsPath, __DIR__.'/Fixtures/fake-sendmail.php -ffrom@mail.com to@mail.com');
    }

    public function testRecipientsAreUsedWhenSet()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support shebangs nor non-blocking standard streams');
        }

        $mail = new Email();
        $mail
            ->from('from@mail.com')
            ->to('to@mail.com')
            ->subject('Subject')
            ->text('Some text')
        ;

        $envelope = new DelayedEnvelope($mail);
        $envelope->setRecipients([new Address('recipient@mail.com')]);

        $sendmailTransport = new SendmailTransport(self::FAKE_SENDMAIL);
        $sendmailTransport->send($mail, $envelope);

        $this->assertStringEqualsFile($this->argsPath, __DIR__.'/Fixtures/fake-sendmail.php -ffrom@mail.com recipient@mail.com');
    }
}
