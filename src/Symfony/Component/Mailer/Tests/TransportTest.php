<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Bridge\Amazon;
use Symfony\Component\Mailer\Bridge\Google;
use Symfony\Component\Mailer\Bridge\Mailchimp;
use Symfony\Component\Mailer\Bridge\Mailgun;
use Symfony\Component\Mailer\Bridge\Postmark;
use Symfony\Component\Mailer\Bridge\Sendgrid;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TransportTest extends TestCase
{
    public function testFromDsnNull()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://null', $dispatcher, null, $logger);
        $this->assertInstanceOf(Transport\NullTransport::class, $transport);
        $p = new \ReflectionProperty(Transport\AbstractTransport::class, 'dispatcher');
        $p->setAccessible(true);
        $this->assertSame($dispatcher, $p->getValue($transport));
    }

    public function testFromDsnSendmail()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://sendmail', $dispatcher, null, $logger);
        $this->assertInstanceOf(Transport\SendmailTransport::class, $transport);
        $p = new \ReflectionProperty(Transport\AbstractTransport::class, 'dispatcher');
        $p->setAccessible(true);
        $this->assertSame($dispatcher, $p->getValue($transport));
    }

    public function testFromDsnSmtp()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://localhost:44?auth_mode=plain&encryption=tls', $dispatcher, null, $logger);
        $this->assertInstanceOf(Transport\Smtp\SmtpTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger);
        $this->assertEquals('localhost', $transport->getStream()->getHost());
        $this->assertEquals('plain', $transport->getAuthMode());
        $this->assertTrue($transport->getStream()->isTLS());
        $this->assertEquals(44, $transport->getStream()->getPort());
    }

    public function testFromInvalidDsn()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "some://" mailer DSN is invalid.');
        Transport::fromDsn('some://');
    }

    public function testNoScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "//sendmail" mailer DSN must contain a transport scheme.');
        Transport::fromDsn('//sendmail');
    }

    public function testFromInvalidDsnNoHost()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "file:///some/path" mailer DSN must contain a mailer name.');
        Transport::fromDsn('file:///some/path');
    }

    public function testFromInvalidTransportName()
    {
        $this->expectException(LogicException::class);
        Transport::fromDsn('api://foobar');
    }

    public function testFromDsnGmail()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@gmail', $dispatcher, null, $logger);
        $this->assertInstanceOf(Google\Smtp\GmailTransport::class, $transport);
        $this->assertEquals('u$er', $transport->getUsername());
        $this->assertEquals('pa$s', $transport->getPassword());
        $this->assertProperties($transport, $dispatcher, $logger);

        $this->expectException(LogicException::class);
        Transport::fromDsn('http://gmail');
    }

    public function testFromDsnMailgun()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, null, $logger);
        $this->assertInstanceOf(Mailgun\Smtp\MailgunTransport::class, $transport);
        $this->assertEquals('u$er', $transport->getUsername());
        $this->assertEquals('pa$s', $transport->getPassword());
        $this->assertProperties($transport, $dispatcher, $logger);

        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, null, $logger);
        $this->assertEquals('smtp.mailgun.org', $transport->getStream()->getHost());

        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=eu', $dispatcher, null, $logger);
        $this->assertEquals('smtp.eu.mailgun.org', $transport->getStream()->getHost());

        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=us', $dispatcher, null, $logger);
        $this->assertEquals('smtp.mailgun.org', $transport->getStream()->getHost());

        $client = $this->createMock(HttpClientInterface::class);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Mailgun\Http\MailgunTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'domain' => 'pa$s',
            'client' => $client,
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->any())->method('getStatusCode')->willReturn(200);
        $message = (new Email())->from('me@me.com')->to('you@you.com')->subject('hello')->text('Hello you');

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages.mime')->willReturn($response);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, $client, $logger);
        $transport->send($message);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.eu.mailgun.net/v3/pa%24s/messages.mime')->willReturn($response);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=eu', $dispatcher, $client, $logger);
        $transport->send($message);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages.mime')->willReturn($response);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=us', $dispatcher, $client, $logger);
        $transport->send($message);

        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Mailgun\Http\Api\MailgunTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'domain' => 'pa$s',
            'client' => $client,
        ]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages')->willReturn($response);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun', $dispatcher, $client, $logger);
        $transport->send($message);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.eu.mailgun.net/v3/pa%24s/messages')->willReturn($response);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=eu', $dispatcher, $client, $logger);
        $transport->send($message);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages')->willReturn($response);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=us', $dispatcher, $client, $logger);
        $transport->send($message);

        $message = (new Email())->from('me@me.com')->to('you@you.com')->subject('hello')->html('test');
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages')->willReturn($response);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=us', $dispatcher, $client, $logger);
        $transport->send($message);

        $stream = fopen('data://text/plain,'.$message->getTextBody(), 'r');
        $message = (new Email())->from('me@me.com')->to('you@you.com')->subject('hello')->html($stream);
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())->method('request')->with('POST', 'https://api.mailgun.net/v3/pa%24s/messages')->willReturn($response);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@mailgun?region=us', $dispatcher, $client, $logger);
        $transport->send($message);

        $this->expectException(LogicException::class);
        Transport::fromDsn('foo://mailgun');
    }

    public function testFromDsnPostmark()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').'@postmark', $dispatcher, null, $logger);
        $this->assertInstanceOf(Postmark\Smtp\PostmarkTransport::class, $transport);
        $this->assertEquals('u$er', $transport->getUsername());
        $this->assertEquals('u$er', $transport->getPassword());
        $this->assertProperties($transport, $dispatcher, $logger);

        $client = $this->createMock(HttpClientInterface::class);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').'@postmark', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Postmark\Http\Api\PostmarkTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'client' => $client,
        ]);

        $this->expectException(LogicException::class);
        Transport::fromDsn('http://postmark');
    }

    public function testFromDsnSendgrid()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').'@sendgrid', $dispatcher, null, $logger);
        $this->assertInstanceOf(Sendgrid\Smtp\SendgridTransport::class, $transport);
        $this->assertEquals('apikey', $transport->getUsername());
        $this->assertEquals('u$er', $transport->getPassword());
        $this->assertProperties($transport, $dispatcher, $logger);

        $client = $this->createMock(HttpClientInterface::class);
        $transport = Transport::fromDsn('api://'.urlencode('u$er').'@sendgrid', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Sendgrid\Http\Api\SendgridTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'client' => $client,
        ]);

        $this->expectException(LogicException::class);
        Transport::fromDsn('http://sendgrid');
    }

    public function testFromDsnAmazonSes()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@ses?region=sun', $dispatcher, null, $logger);
        $this->assertInstanceOf(Amazon\Smtp\SesTransport::class, $transport);
        $this->assertEquals('u$er', $transport->getUsername());
        $this->assertEquals('pa$s', $transport->getPassword());
        $this->assertStringContainsString('.sun.', $transport->getStream()->getHost());
        $this->assertProperties($transport, $dispatcher, $logger);

        $client = $this->createMock(HttpClientInterface::class);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').':'.urlencode('pa$s').'@ses?region=sun', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Amazon\Http\SesTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'accessKey' => 'u$er',
            'secretKey' => 'pa$s',
            'region' => 'sun',
            'client' => $client,
        ]);

        $transport = Transport::fromDsn('api://'.urlencode('u$er').':'.urlencode('pa$s').'@ses?region=sun', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Amazon\Http\Api\SesTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'accessKey' => 'u$er',
            'secretKey' => 'pa$s',
            'region' => 'sun',
            'client' => $client,
        ]);

        $this->expectException(LogicException::class);
        Transport::fromDsn('foo://ses');
    }

    public function testFromDsnMailchimp()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://'.urlencode('u$er').':'.urlencode('pa$s').'@mandrill', $dispatcher, null, $logger);
        $this->assertInstanceOf(Mailchimp\Smtp\MandrillTransport::class, $transport);
        $this->assertEquals('u$er', $transport->getUsername());
        $this->assertEquals('pa$s', $transport->getPassword());
        $this->assertProperties($transport, $dispatcher, $logger);

        $client = $this->createMock(HttpClientInterface::class);
        $transport = Transport::fromDsn('http://'.urlencode('u$er').'@mandrill', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Mailchimp\Http\MandrillTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'client' => $client,
        ]);

        $transport = Transport::fromDsn('api://'.urlencode('u$er').'@mandrill', $dispatcher, $client, $logger);
        $this->assertInstanceOf(Mailchimp\Http\Api\MandrillTransport::class, $transport);
        $this->assertProperties($transport, $dispatcher, $logger, [
            'key' => 'u$er',
            'client' => $client,
        ]);

        $this->expectException(LogicException::class);
        Transport::fromDsn('foo://mandrill');
    }

    public function testFromDsnFailover()
    {
        $user = 'user';
        $pass = 'pass';
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://example.com || smtp://'.urlencode($user).'@example.com || smtp://'.urlencode($user).':'.urlencode($pass).'@example.com', $dispatcher, null, $logger);
        $this->assertInstanceOf(Transport\FailoverTransport::class, $transport);
        $p = new \ReflectionProperty(Transport\RoundRobinTransport::class, 'transports');
        $p->setAccessible(true);
        $transports = $p->getValue($transport);
        $this->assertCount(3, $transports);
        foreach ($transports as $transport) {
            $this->assertProperties($transport, $dispatcher, $logger);
        }
        $this->assertSame('', $transports[0]->getUsername());
        $this->assertSame('', $transports[0]->getPassword());
        $this->assertSame($user, $transports[1]->getUsername());
        $this->assertSame('', $transports[1]->getPassword());
        $this->assertSame($user, $transports[2]->getUsername());
        $this->assertSame($pass, $transports[2]->getPassword());
    }

    public function testFromDsnRoundRobin()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $transport = Transport::fromDsn('smtp://null && smtp://null && smtp://null', $dispatcher, null, $logger);
        $this->assertInstanceOf(Transport\RoundRobinTransport::class, $transport);
        $p = new \ReflectionProperty(Transport\RoundRobinTransport::class, 'transports');
        $p->setAccessible(true);
        $transports = $p->getValue($transport);
        $this->assertCount(3, $transports);
        foreach ($transports as $transport) {
            $this->assertProperties($transport, $dispatcher, $logger);
        }
    }

    private function assertProperties(Transport\TransportInterface $transport, EventDispatcherInterface $dispatcher, LoggerInterface $logger, array $props = [])
    {
        $p = new \ReflectionProperty(Transport\AbstractTransport::class, 'dispatcher');
        $p->setAccessible(true);
        $this->assertSame($dispatcher, $p->getValue($transport));

        $p = new \ReflectionProperty(Transport\AbstractTransport::class, 'logger');
        $p->setAccessible(true);
        $this->assertSame($logger, $p->getValue($transport));

        foreach ($props as $prop => $value) {
            $p = new \ReflectionProperty($transport, $prop);
            $p->setAccessible(true);
            $this->assertEquals($value, $p->getValue($transport));
        }
    }
}
