<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat\Tests;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;

final class FakeChatTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @dataProvider missingRequiredDependencyProvider
     */
    public function testMissingRequiredDependency(?MailerInterface $mailer, ?LoggerInterface $logger, string $dsn, string $message)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $factory = new FakeChatTransportFactory($mailer, $logger);
        $factory->create(new Dsn($dsn));
    }

    /**
     * @dataProvider missingOptionalDependencyProvider
     */
    public function testMissingOptionalDependency(?MailerInterface $mailer, ?LoggerInterface $logger, string $dsn)
    {
        $factory = new FakeChatTransportFactory($mailer, $logger);
        $transport = $factory->create(new Dsn($dsn));

        $this->assertSame($dsn, (string) $transport);
    }

    public function createFactory(): FakeChatTransportFactory
    {
        return new FakeChatTransportFactory($this->createMock(MailerInterface::class), $this->createMock(LoggerInterface::class));
    }

    public static function createProvider(): iterable
    {
        yield [
            'fakechat+email://default?to=recipient@email.net&from=sender@email.net',
            'fakechat+email://default?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakechat+email://mailchimp?to=recipient@email.net&from=sender@email.net',
            'fakechat+email://mailchimp?to=recipient@email.net&from=sender@email.net',
        ];

        yield [
            'fakechat+logger://default',
            'fakechat+logger://default',
        ];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['fakechat+email://default?to=recipient@email.net'];
        yield 'missing option: to' => ['fakechat+email://default?from=sender@email.net'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'fakechat+email://default?to=recipient@email.net&from=sender@email.net'];
        yield [false, 'somethingElse://default?to=recipient@email.net&from=sender@email.net'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['fakechat+email://default?to=recipient@email.net'];
        yield 'missing to' => ['fakechat+email://default?from=recipient@email.net'];
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
            'fakechat+email://default?to=recipient@email.net&from=sender@email.net',
            sprintf($exceptionMessage, 'fakechat+email', MailerInterface::class),
        ];
        yield 'missing logger' => [
            $this->createMock(MailerInterface::class),
            null,
            'fakechat+logger://default',
            sprintf($exceptionMessage, 'fakechat+logger', LoggerInterface::class),
        ];
    }

    public function missingOptionalDependencyProvider(): iterable
    {
        yield 'missing logger' => [
            $this->createMock(MailerInterface::class),
            null,
            'fakechat+email://default?to=recipient@email.net&from=sender@email.net',
        ];
        yield 'missing mailer' => [
            null,
            $this->createMock(LoggerInterface::class),
            'fakechat+logger://default',
        ];
    }
}
