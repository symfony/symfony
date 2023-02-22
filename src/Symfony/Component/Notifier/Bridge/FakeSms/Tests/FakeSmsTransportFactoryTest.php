<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;

final class FakeSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @dataProvider missingRequiredDependencyProvider
     */
    public function testMissingRequiredDependency(?MailerInterface $mailer, ?LoggerInterface $logger, string $dsn, string $message)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $factory = new FakeSmsTransportFactory($mailer, $logger);
        $factory->create(new Dsn($dsn));
    }

    /**
     * @dataProvider missingOptionalDependencyProvider
     */
    public function testMissingOptionalDependency(?MailerInterface $mailer, ?LoggerInterface $logger, string $dsn)
    {
        $factory = new FakeSmsTransportFactory($mailer, $logger);
        $transport = $factory->create(new Dsn($dsn));

        $this->assertSame($dsn, (string) $transport);
    }

    public function createFactory(): FakeSmsTransportFactory
    {
        return new FakeSmsTransportFactory($this->createMock(MailerInterface::class), $this->createMock(LoggerInterface::class));
    }

    public static function createProvider(): iterable
    {
        yield [
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakesms+email://mailchimp?to=recipient@email.net&from=sender@email.net',
            'fakesms+email://mailchimp?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakesms+logger://default',
            'fakesms+logger://default',
        ];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['fakesms+email://default?to=recipient@email.net'];
        yield 'missing option: to' => ['fakesms+email://default?from=sender@email.net'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'fakesms+email://default?to=recipient@email.net&from=sender@email.net'];
        yield [false, 'somethingElse://default?to=recipient@email.net&from=sender@email.net'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['fakesms+email://default?to=recipient@email.net'];
        yield 'missing to' => ['fakesms+email://default?from=recipient@email.net'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default?to=recipient@email.net&from=sender@email.net'];
    }

    public function missingRequiredDependencyProvider(): iterable
    {
        $exceptionMessage = 'Cannot create a transport for scheme "%s" without providing an implementation of "%s".';
        yield 'missing mailer' => [
            null,
            $this->createMock(LoggerInterface::class),
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
            sprintf($exceptionMessage, 'fakesms+email', MailerInterface::class),
        ];
        yield 'missing logger' => [
            $this->createMock(MailerInterface::class),
            null,
            'fakesms+logger://default',
            sprintf($exceptionMessage, 'fakesms+logger', LoggerInterface::class),
        ];
    }

    public function missingOptionalDependencyProvider(): iterable
    {
        yield 'missing logger' => [
            $this->createMock(MailerInterface::class),
            null,
            'fakesms+email://default?to=recipient@email.net&from=sender@email.net',
        ];
        yield 'missing mailer' => [
            null,
            $this->createMock(LoggerInterface::class),
            'fakesms+logger://default',
        ];
    }
}
