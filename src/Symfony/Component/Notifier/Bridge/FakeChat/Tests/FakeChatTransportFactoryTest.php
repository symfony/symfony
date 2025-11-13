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
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

final class FakeChatTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function testMissingRequiredMailerDependency()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot create a transport for scheme "fakechat+email" without providing an implementation of "Symfony\Component\Mailer\MailerInterface".');

        $factory = new FakeChatTransportFactory(null, $this->createStub(LoggerInterface::class));
        $factory->create(new Dsn('fakechat+email://default?to=recipient@email.net&from=sender@email.net'));
    }

    public function testMissingRequiredLoggerDependency()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot create a transport for scheme "fakechat+logger" without providing an implementation of "Psr\Log\LoggerInterface".');

        $factory = new FakeChatTransportFactory($this->createStub(MailerInterface::class));
        $factory->create(new Dsn('fakechat+logger://default'));
    }

    public function testMissingOptionalLoggerDependency()
    {
        $factory = new FakeChatTransportFactory($this->createStub(MailerInterface::class));
        $transport = $factory->create(new Dsn('fakechat+email://default?to=recipient@email.net&from=sender@email.net'));

        $this->assertSame('fakechat+email://default?to=recipient@email.net&from=sender@email.net', (string) $transport);
    }

    public function testMissingOptionalMailerDependency()
    {
        $factory = new FakeChatTransportFactory(null, $this->createStub(LoggerInterface::class));
        $transport = $factory->create(new Dsn('fakechat+logger://default'));

        $this->assertSame('fakechat+logger://default', (string) $transport);
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
}
