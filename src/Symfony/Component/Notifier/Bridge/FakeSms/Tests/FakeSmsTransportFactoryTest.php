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
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

final class FakeSmsTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function testMissingRequiredMailerDependency()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot create a transport for scheme "fakesms+email" without providing an implementation of "Symfony\Component\Mailer\MailerInterface".');

        $factory = new FakeSmsTransportFactory(null, $this->createStub(LoggerInterface::class));
        $factory->create(new Dsn('fakesms+email://default?to=recipient@email.net&from=sender@email.net'));
    }

    public function testMissingRequiredDependency()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot create a transport for scheme "fakesms+logger" without providing an implementation of "Psr\Log\LoggerInterface".');

        $factory = new FakeSmsTransportFactory($this->createStub(MailerInterface::class));
        $factory->create(new Dsn('fakesms+logger://default'));
    }

    public function testMissingOptionalLoggerDependency()
    {
        $factory = new FakeSmsTransportFactory($this->createStub(MailerInterface::class));
        $transport = $factory->create(new Dsn('fakesms+email://default?to=recipient@email.net&from=sender@email.net'));

        $this->assertSame('fakesms+email://default?to=recipient@email.net&from=sender@email.net', (string) $transport);
    }

    public function testMissingOptionalMailerDependency()
    {
        $factory = new FakeSmsTransportFactory(null, $this->createStub(LoggerInterface::class));
        $transport = $factory->create(new Dsn('fakesms+logger://default'));

        $this->assertSame('fakesms+logger://default', (string) $transport);
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
}
