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
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\NativeTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class NativeTransportFactoryTest extends TestCase
{
    public static $fakeConfiguration = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $namespace = str_replace('\\Tests\\', '\\', __NAMESPACE__);

        $current = static::class;

        $eval = <<<EOT
namespace $namespace;

function ini_get(\$key)
{
    \$vals = \\$current::\$fakeConfiguration;
    return \$vals[\$key] ?? '';
}
EOT;
        eval($eval);
    }

    public function testCreateWithNotSupportedScheme()
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('The "sendmail" scheme is not supported');

        $sut = new NativeTransportFactory();
        $sut->create(Dsn::fromString('sendmail://default'));
    }

    public function testCreateSendmailWithNoSendmailPath()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sendmail_path is not configured');

        $sut = new NativeTransportFactory();
        $sut->create(Dsn::fromString('native://default'));
    }

    public static function provideCreateSendmailWithNoHostOrNoPort(): \Generator
    {
        yield ['native://default', '', '', ''];
        yield ['native://default', '', 'localhost', ''];
        yield ['native://default', '', '', '25'];
    }

    /**
     * @dataProvider provideCreateSendmailWithNoHostOrNoPort
     */
    public function testCreateSendmailWithNoHostOrNoPort(string $dsn, string $sendmaiPath, string $smtp, string $smtpPort)
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test only run on Windows.');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('smtp or smtp_port is not configured');

        self::$fakeConfiguration = [
            'sendmail_path' => $sendmaiPath,
            'smtp' => $smtp,
            'smtp_port' => $smtpPort,
        ];

        $sut = new NativeTransportFactory();
        $sut->create(Dsn::fromString($dsn));
    }

    public static function provideCreate(): \Generator
    {
        yield ['native://default', '/usr/sbin/sendmail -t -i', '', '', new SendmailTransport('/usr/sbin/sendmail -t -i')];

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $socketStream = new SocketStream();
            $socketStream->setHost('myhost.tld');
            $socketStream->setPort(25);
            $socketStream->disableTls();
            yield ['native://default', '', 'myhost.tld', '25', new SmtpTransport($socketStream)];

            $socketStreamTls = new SocketStream();
            $socketStreamTls->setHost('myhost.tld');
            $socketStreamTls->setPort(465);
            yield ['native://default', '', 'myhost.tld', '465', new SmtpTransport($socketStreamTls)];
        }
    }

    /**
     * @dataProvider provideCreate
     */
    public function testCreate(string $dsn, string $sendmailPath, string $smtp, string $smtpPort, TransportInterface $expectedTransport)
    {
        self::$fakeConfiguration = [
            'sendmail_path' => $sendmailPath,
            'SMTP' => $smtp,
            'smtp_port' => $smtpPort,
        ];

        $sut = new NativeTransportFactory();
        $transport = $sut->create(Dsn::fromString($dsn));

        $this->assertEquals($expectedTransport, $transport);
    }
}
